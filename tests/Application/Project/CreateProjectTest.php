<?php

namespace App\Tests\Application\Project;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;
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

    private function pngUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'project_test_').'.png';
        file_put_contents($path, base64_decode(self::PNG_1X1));
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'lamp.png', 'image/png', null, true);
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }
}
