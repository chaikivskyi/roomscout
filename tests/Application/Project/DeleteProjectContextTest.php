<?php

namespace App\Tests\Application\Project;

use App\CatalogSearch\Entity\ProjectContextEmbedding;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectContext;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectProductMatchFactory;
use App\Tests\Factory\UserFactory;
use Pgvector\Vector;
use Symfony\Component\Uid\Uuid;

final class DeleteProjectContextTest extends ApiTestCase
{
    public function testDeletesContextWithItsMatchesAndEmbedding(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $context = ProjectContextFactory::createOne(['project' => $project]);
        $sibling = ProjectContextFactory::createOne(['project' => $project]);
        $contextId = $context->getId();
        $siblingId = $sibling->getId();
        $projectId = $project->getId();

        ProjectProductMatchFactory::createOne(['context' => $context]);
        ProjectProductMatchFactory::createOne(['context' => $sibling]);
        $this->entityManager()->persist(new ProjectContextEmbedding(
            $context,
            new Vector(array_fill(0, 1536, 0.0)),
            'embed-test-1.0',
            new \DateTimeImmutable(),
        ));
        $this->entityManager()->flush();

        $response = $this->authClient($this->tokenFor($user))
            ->request('DELETE', '/api/projects/'.$projectId->toRfc4122().'/contexts/'.$contextId->toRfc4122());

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());

        // The DB-level cascade bypasses the identity map.
        $this->entityManager()->clear();

        self::assertNull($this->entityManager()->find(ProjectContext::class, $contextId));
        self::assertSame(0, $this->entityManager()->getRepository(ProjectProductMatch::class)->count(['context' => $contextId]));
        self::assertSame(0, $this->entityManager()->getRepository(ProjectContextEmbedding::class)->count(['context' => $contextId]));

        // The project and its other contexts survive.
        self::assertNotNull($this->entityManager()->find(Project::class, $projectId));
        self::assertNotNull($this->entityManager()->find(ProjectContext::class, $siblingId));
        self::assertSame(1, $this->entityManager()->getRepository(ProjectProductMatch::class)->count(['context' => $siblingId]));
    }

    public function testSecondDeleteReturns404(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $context = ProjectContextFactory::createOne(['project' => $project]);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/contexts/'.$context->getId()->toRfc4122();

        $client->request('DELETE', $url);
        self::assertResponseStatusCodeSame(204);

        $client->request('DELETE', $url);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownOrForeignContextReturns404(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $otherProjectContext = ProjectContextFactory::createOne([
            'project' => ProjectFactory::new(['user' => $user]),
        ]);

        $client = $this->authClient($this->tokenFor($user));
        $base = '/api/projects/'.$project->getId()->toRfc4122().'/contexts/';

        $client->request('DELETE', $base.Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);

        $client->request('DELETE', $base.'not-a-uuid');
        self::assertResponseStatusCodeSame(404);

        // A real context id nested under the wrong project is not found either.
        $client->request('DELETE', $base.$otherProjectContext->getId()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->entityManager()->find(ProjectContext::class, $otherProjectContext->getId()));
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $context = ProjectContextFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('DELETE', '/api/projects/'.$context->getProject()->getId()->toRfc4122().'/contexts/'.$context->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->entityManager()->find(ProjectContext::class, $context->getId()));
    }

    public function testRequiresAuthentication(): void
    {
        $context = ProjectContextFactory::createOne();

        static::createClient()->request('DELETE', '/api/projects/'.$context->getProject()->getId()->toRfc4122().'/contexts/'.$context->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(401);
    }
}
