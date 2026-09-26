<?php

namespace App\Tests\Application\Project;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Project\Service\ProjectImageStorage;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Craue\ConfigBundle\Util\Config;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CreateProjectTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private array $tempFiles = [];

    /** @var array<string, ?string> */
    private array $originalSettings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $settings = $this->settings();
        $this->originalSettings = [
            'free_search_count' => $settings->get('free_search_count'),
            'free_max_image_size_mb' => $settings->get('free_max_image_size_mb'),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];

        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        $settings = $this->settings();
        foreach ($this->originalSettings as $name => $value) {
            $settings->set($name, $value);
        }

        parent::tearDown();
    }

    public function testTokenOfADeletedUserIsRejectedWith401(): void
    {
        $user = UserFactory::createOne();
        $token = $this->tokenFor($user);
        $this->entityManager()->remove($user);
        $this->entityManager()->flush();

        $this->authClient($token)->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAnOwnerDeletedMidRequestIsRejectedWith401(): void
    {
        $user = UserFactory::createOne();
        $userId = $user->getId();
        $client = $this->authClient($this->tokenFor($user));

        $entityManager = $this->entityManager();
        static::getContainer()->get('event_dispatcher')->addListener(
            KernelEvents::CONTROLLER,
            static function (ControllerEvent $event) use ($entityManager, $userId): void {
                if (!$event->isMainRequest()) {
                    return;
                }

                $owner = $entityManager->find(User::class, $userId);
                self::assertNotNull($owner);
                $entityManager->remove($owner);
                $entityManager->flush();
            },
        );

        $client->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(0, $entityManager->getRepository(Project::class)->count([]));
        self::assertSame([], $this->storage()->listContents('', true)
            ->filter(static fn (StorageAttributes $item): bool => $item->isFile())
            ->toArray(), 'The processor must remove the stored image when the command fails.');
    }

    public function testCreatesProjectFromImageAndPrompt(): void
    {
        $user = UserFactory::createOne();

        $response = $this->authClient($this->tokenFor($user))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['context' => ['prompt' => 'find a similar lamp', 'status' => 'processing']]);

        $data = $response->toArray();
        $contextData = $data['context'];
        self::assertIsArray($contextData);
        self::assertNotEmpty($data['id']);
        self::assertNotEmpty($data['createdAt']);
        self::assertNotEmpty($contextData['id']);
        self::assertNotEmpty($contextData['createdAt']);
        self::assertArrayNotHasKey('prompt', $data, 'The project must not carry its context\'s fields.');

        $contexts = $this->entityManager()->getRepository(ProjectContext::class);
        $context = $contexts->findOneBy(['prompt' => 'find a similar lamp']);
        self::assertNotNull($context);
        self::assertSame(ProjectContextStatus::Processing, $context->getStatus());
        self::assertSame($contextData['id'], $context->getId()->toRfc4122());

        $project = $context->getProject();
        self::assertSame($data['id'], $project->getId()->toRfc4122());
        self::assertTrue($user->getId()->equals($project->getUser()->getId()));
        self::assertSame(1, $contexts->count(['project' => $project->getId()]));

        $versionRepository = static::getContainer()->get(ProjectImageVersionRepository::class);
        self::assertSame(1, $versionRepository->count(['project' => $project->getId()]));
        $version = $versionRepository->findLatestForProject($project->getId());
        self::assertNotNull($version);
        self::assertStringEndsWith('/image.png', $version->getImagePath());

        self::assertTrue($this->storage()->fileExists($version->getImagePath()));
        self::assertSame(base64_decode(self::PNG_1X1), $this->storage()->read($version->getImagePath()));
    }

    public function testAGuestCanCreateAndOwnAProject(): void
    {
        $guest = UserFactory::new()->guest()->create();
        $client = $this->authClient($this->tokenFor($guest));

        $response = $client->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $projectId = $response->toArray()['id'];
        self::assertIsString($projectId);

        $client->request('GET', '/api/projects/'.$projectId);
        self::assertResponseIsSuccessful();

        $listed = $client->request('GET', '/api/projects')->toArray();
        $members = $listed['member'];
        self::assertIsArray($members);
        self::assertCount(1, $members);
        $firstMember = $members[0];
        self::assertIsArray($firstMember);
        self::assertSame($projectId, $firstMember['id']);
    }

    public function testAnUploadIsStoredUnderItsOwnersPrefix(): void
    {
        $owner = UserFactory::createOne();

        $this->authClient($this->tokenFor($owner))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $prefix = ProjectImageStorage::prefixFor($owner->getId());
        self::assertTrue(
            $this->storage()->directoryExists($prefix),
            'The purge deletes a guest\'s files by owner prefix, so uploads must live under one.',
        );
        self::assertStringNotContainsString(
            (string) $owner->getId(),
            $prefix,
            'The prefix is public in image URLs, so it must not expose the owner id.',
        );
    }

    public function testAGuestCannotSeeAnotherGuestsProject(): void
    {
        $owner = UserFactory::new()->guest()->create();
        $project = ProjectFactory::createOne(['user' => $owner]);

        $this->authClient($this->tokenFor(UserFactory::new()->guest()->create()))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }

    public function testRequiresAuthentication(): void
    {
        static::createClient()->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsMissingPrompt(): void
    {
        $this->authClient($this->tokenFor(UserFactory::createOne()))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'prompt']]]);
    }

    public function testRejectsMissingImage(): void
    {
        $this->authClient($this->tokenFor(UserFactory::createOne()))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'image']]]);
    }

    public function testRejectsNonImageFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'project_test_').'.txt';
        file_put_contents($path, 'not an image');
        $this->tempFiles[] = $path;
        $upload = new UploadedFile($path, 'notes.txt', 'text/plain', null, true);

        $this->authClient($this->tokenFor(UserFactory::createOne()))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $upload],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'image']]]);
    }

    public function testRejectsJsonContentType(): void
    {
        $this->authClient($this->tokenFor(UserFactory::createOne()))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['prompt' => 'find a similar lamp'],
        ]);

        self::assertResponseStatusCodeSame(415);
    }

    public function testCreatingBeyondTheFreeSearchCountReturns422(): void
    {
        $this->settings()->set('free_search_count', '1');

        $user = UserFactory::createOne();
        ProjectFactory::createOne(['user' => $user]);

        $this->authClient($this->tokenFor($user))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [['message' => 'You have used all 1 of your free searches.']],
        ]);
    }

    public function testANullFreeSearchCountMeansNoLimit(): void
    {
        $this->settings()->set('free_search_count', null);

        $user = UserFactory::createOne();
        ProjectFactory::createOne(['user' => $user]);
        ProjectFactory::createOne(['user' => $user]);
        ProjectFactory::createOne(['user' => $user]);

        $this->authClient($this->tokenFor($user))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(201, 'A NULL free_search_count must mean "no limit", not "cast to 0".');
    }

    public function testAZeroFreeSearchCountRefusesEvenAFirstProject(): void
    {
        $this->settings()->set('free_search_count', '0');

        $user = UserFactory::createOne();

        $this->authClient($this->tokenFor($user))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(422, 'A free_search_count of "0" is a real configuration, distinct from NULL, and must be enforced.');
        self::assertJsonContains([
            'violations' => [['message' => 'You have used all 0 of your free searches.']],
        ]);
    }

    public function testTheQuotaAppliesToGuestsToo(): void
    {
        $this->settings()->set('free_search_count', '1');

        $guest = UserFactory::new()->guest()->create();
        ProjectFactory::createOne(['user' => $guest]);

        $this->authClient($this->tokenFor($guest))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload()],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingAProjectSucceedsWhenAConfigSettingRowIsMissing(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement("DELETE FROM craue_config_setting WHERE name = 'free_search_count'");

        try {
            $this->authClient($this->tokenFor(UserFactory::createOne()))->request('POST', '/api/projects', [
                'headers' => ['Content-Type' => 'multipart/form-data'],
                'extra' => [
                    'parameters' => ['prompt' => 'find a similar lamp'],
                    'files' => ['image' => $this->pngUpload()],
                ],
            ]);

            self::assertResponseStatusCodeSame(
                201,
                'A missing free_search_count row must behave like NULL ("no limit"), not throw a 500.',
            );
        } finally {
            $connection->executeStatement(
                "INSERT INTO craue_config_setting (name, value, section) VALUES ('free_search_count', :value, 'search')",
                ['value' => $this->originalSettings['free_search_count']],
            );
        }
    }

    public function testAnImageOverTheFreeSizeLimitReturns422AndStoresNothing(): void
    {
        $this->settings()->set('free_max_image_size_mb', '1');

        $this->authClient($this->tokenFor(UserFactory::createOne()))->request('POST', '/api/projects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['prompt' => 'find a similar lamp'],
                'files' => ['image' => $this->pngUpload(2_000_000)],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [['propertyPath' => 'image', 'message' => 'The image must not be larger than 1 MB.']],
        ]);
        self::assertCount(0, $this->storage()->listContents('')->toArray());
    }

    private function pngUpload(?int $padToBytes = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'project_test_').'.png';
        $bytes = base64_decode(self::PNG_1X1);
        file_put_contents($path, $bytes);

        if (null !== $padToBytes && $padToBytes > \strlen($bytes)) {
            file_put_contents($path, str_repeat("\0", $padToBytes - \strlen($bytes)), \FILE_APPEND);
        }

        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'lamp.png', 'image/png', null, true);
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }

    private function settings(): Config
    {
        return static::getContainer()->get(Config::class);
    }
}
