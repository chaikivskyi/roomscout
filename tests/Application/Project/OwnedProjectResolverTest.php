<?php

namespace App\Tests\Application\Project;

use App\Identity\Entity\User;
use App\Project\Exception\ProjectNotFound;
use App\Project\Exception\ProjectNotOwned;
use App\Project\Service\OwnedProjectResolver;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class OwnedProjectResolverTest extends ApiTestCase
{
    public function testOwnerIsResolvedWhenTheUserInstanceIsNotIdentityMapped(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $projectId = $project->getId();

        $this->entityManager()->clear();
        $actor = $this->entityManager()->find(User::class, $user->getId());
        self::assertNotNull($actor);
        $actorId = $actor->getId();
        $this->entityManager()->clear();

        $resolved = $this->resolver()->resolve($projectId, $actorId);

        self::assertTrue($resolved->getId()->equals($projectId));
        self::assertNotSame($actorId, $resolved->getUser()->getId(), 'The test needs two distinct Uuid instances to be meaningful.');
    }

    public function testForeignProjectIsDenied(): void
    {
        $project = ProjectFactory::createOne();
        $stranger = UserFactory::createOne();

        $this->expectException(ProjectNotOwned::class);
        $this->resolver()->resolve($project->getId(), $stranger->getId());
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $user = UserFactory::createOne();

        $this->expectException(ProjectNotFound::class);
        $this->resolver()->resolve(Uuid::v7(), $user->getId());
    }

    private function resolver(): OwnedProjectResolver
    {
        return static::getContainer()->get(OwnedProjectResolver::class);
    }
}
