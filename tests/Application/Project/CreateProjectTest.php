<?php

namespace App\Tests\Application\Project;

use App\Project\Entity\Project;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
        self::assertJsonContains(['prompt' => 'find a similar lamp', 'status' => 'processing']);

        $data = $response->toArray();
        self::assertNotEmpty($data['id']);
        self::assertNotEmpty($data['createdAt']);

        $query = $this->entityManager()->getRepository(Project::class)
            ->findOneBy(['prompt' => 'find a similar lamp']);
        self::assertNotNull($query);
        self::assertSame($data['id'], $query->getId()->toRfc4122());
        self::assertSame($user->getId(), $query->getUser()->getId());
        self::assertStringEndsWith('/image.png', $query->getImagePath());

        self::assertTrue($this->storage()->fileExists($query->getImagePath()));
        self::assertSame(base64_decode(self::PNG_1X1), $this->storage()->read($query->getImagePath()));
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
