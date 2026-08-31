<?php

namespace App\Tests\Application\Project;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ListProjectsTest extends ApiTestCase
{
    public function testListsOwnProjectsNewestFirstWithPromptAndImage(): void
    {
        $user = UserFactory::createOne();

        $older = ProjectFactory::createOne(['user' => $user]);
        ProjectContextFactory::createOne(['project' => $older, 'prompt' => 'a beige sofa']);
        ProjectContextFactory::createOne(['project' => $older, 'prompt' => 'the same sofa but green']);
        ProjectImageVersionFactory::createOne(['project' => $older, 'imagePath' => 'uploaded/image.jpg']);
        ProjectImageVersionFactory::createOne(['project' => $older, 'imagePath' => 'placed/image.png']);

        $newer = ProjectFactory::createOne(['user' => $user]);
        ProjectContextFactory::createOne(['project' => $newer, 'prompt' => 'a walnut table']);

        ProjectFactory::createOne();

        $response = $this->authClient($this->tokenFor($user))->request('GET', '/api/projects');

        self::assertResponseIsSuccessful();

        $data = self::decode($response);
        self::assertSame(2, $data['totalItems']);
        self::assertSame(
            [$newer->getId()->toRfc4122(), $older->getId()->toRfc4122()],
            array_column($data['member'], 'id'),
        );
        self::assertSame(['a walnut table', 'a beige sofa'], array_column($data['member'], 'prompt'));
        self::assertSame(
            [null, 'http://localhost/uploads/project/placed/image.png'],
            array_column($data['member'], 'imageUrl'),
        );
        self::assertNotEmpty($data['member'][0]['createdAt']);

        self::assertEqualsCanonicalizing(
            ['id', 'prompt', 'imageUrl', 'createdAt'],
            array_values(array_filter(
                array_keys($data['member'][0]),
                static fn (string $key) => !str_starts_with($key, '@'),
            )),
            'The project list payload must expose exactly these fields.',
        );
    }

    public function testProjectWithoutContextsExposesANullPrompt(): void
    {
        $user = UserFactory::createOne();
        ProjectFactory::createOne(['user' => $user]);

        $data = self::decode($this->authClient($this->tokenFor($user))->request('GET', '/api/projects'));

        self::assertResponseIsSuccessful();
        self::assertSame(1, $data['totalItems']);
        self::assertNull($data['member'][0]['prompt']);
    }

    public function testPromptFollowsTheOldestSurvivingContext(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $initial = ProjectContextFactory::createOne(['project' => $project, 'prompt' => 'a beige sofa']);
        ProjectContextFactory::createOne(['project' => $project, 'prompt' => 'the same sofa but green']);

        $client = $this->authClient($this->tokenFor($user));
        $projectUrl = '/api/projects/'.$project->getId()->toRfc4122();

        self::assertSame('a beige sofa', self::decode($client->request('GET', '/api/projects'))['member'][0]['prompt']);

        $client->request('DELETE', $projectUrl.'/contexts/'.$initial->getId()->toRfc4122());
        self::assertResponseStatusCodeSame(204);

        self::assertSame(
            'the same sofa but green',
            self::decode($client->request('GET', '/api/projects'))['member'][0]['prompt'],
        );
    }

    public function testPaginatesAtFifteenPerPage(): void
    {
        $user = UserFactory::createOne();
        ProjectFactory::createMany(16, ['user' => $user]);

        $client = $this->authClient($this->tokenFor($user));

        $first = self::decode($client->request('GET', '/api/projects'));
        self::assertSame(16, $first['totalItems']);
        self::assertCount(15, $first['member']);

        $second = self::decode($client->request('GET', '/api/projects?page=2'));
        self::assertSame(16, $second['totalItems']);
        self::assertCount(1, $second['member']);
        self::assertNotContains($second['member'][0]['id'], array_column($first['member'], 'id'));
    }

    public function testUserWithoutProjectsGetsAnEmptyCollection(): void
    {
        $data = self::decode($this->authClient($this->tokenFor(UserFactory::createOne()))
            ->request('GET', '/api/projects'));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/api/projects');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array{totalItems: int, member: list<array{id: string, prompt: ?string, imageUrl: ?string, createdAt: string}>}
     */
    private static function decode(ResponseInterface $response): array
    {
        /** @var array{totalItems: int, member: list<array{id: string, prompt: ?string, imageUrl: ?string, createdAt: string}>} $data */
        $data = $response->toArray();

        return $data;
    }
}
