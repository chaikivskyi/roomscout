<?php

namespace App\Tests\Application\Project;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class ReadProjectTest extends ApiTestCase
{
    public function testReturnsIdCreatedAtAndTheLatestImageUrl(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        ProjectImageVersionFactory::createOne(['project' => $project, 'imagePath' => 'uploaded/image.jpg']);
        ProjectImageVersionFactory::createOne(['project' => $project, 'imagePath' => 'placed/image.png']);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => $project->getId()->toRfc4122(),
            'imageUrl' => 'http://localhost/uploads/project/placed/image.png',
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        self::assertEqualsCanonicalizing(
            ['id', 'createdAt', 'imageUrl'],
            array_values(array_filter(array_keys($data), static fn (string $key) => !str_starts_with($key, '@'))),
            'The project payload must expose exactly these fields.',
        );
    }

    public function testProjectWithoutImageVersionsExposesANullImageUrl(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['imageUrl' => null]);
    }

    public function testUnknownProjectReturns404(): void
    {
        $client = $this->authClient($this->tokenFor(UserFactory::createOne()));

        $client->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/api/projects/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $project = ProjectFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }

    public function testRequiresAuthentication(): void
    {
        $project = ProjectFactory::createOne();

        static::createClient()->request('GET', '/api/projects/'.$project->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(401);
    }
}
