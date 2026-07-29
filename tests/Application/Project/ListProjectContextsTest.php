<?php

namespace App\Tests\Application\Project;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ListProjectContextsTest extends ApiTestCase
{
    public function testListsOwnProjectContextsInCreationOrder(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $initial = ProjectContextFactory::new(['project' => $project, 'prompt' => 'a beige sofa'])->completed()->create();
        $later = ProjectContextFactory::createOne(['project' => $project, 'prompt' => 'the same sofa but green']);

        // Another project's context must not leak into this listing.
        ProjectContextFactory::createOne(['project' => ProjectFactory::new(['user' => $user])]);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/contexts'));

        self::assertResponseIsSuccessful();
        self::assertSame(2, $data['totalItems']);
        self::assertSame(
            [$initial->getId()->toRfc4122(), $later->getId()->toRfc4122()],
            array_column($data['member'], 'id'),
        );
        self::assertSame(['a beige sofa', 'the same sofa but green'], array_column($data['member'], 'prompt'));
        self::assertSame(['completed', 'processing'], array_column($data['member'], 'status'));
        self::assertNotEmpty($data['member'][0]['createdAt']);
    }

    public function testProjectWithoutContextsReturnsEmptyCollection(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/contexts'));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testUnknownProjectReturns404(): void
    {
        $client = $this->authClient($this->tokenFor(UserFactory::createOne()));

        $client->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122().'/contexts');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/api/projects/not-a-uuid/contexts');
        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $context = ProjectContextFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('GET', '/api/projects/'.$context->getProject()->getId()->toRfc4122().'/contexts');

        self::assertResponseStatusCodeSame(403);
    }

    public function testRequiresAuthentication(): void
    {
        $project = ProjectFactory::createOne();

        static::createClient()->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/contexts');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array{totalItems: int, member: list<array{id: string, prompt: string, status: string, createdAt: string}>}
     */
    private static function decode(ResponseInterface $response): array
    {
        /** @var array{totalItems: int, member: list<array{id: string, prompt: string, status: string, createdAt: string}>} $data */
        $data = $response->toArray();

        return $data;
    }
}
