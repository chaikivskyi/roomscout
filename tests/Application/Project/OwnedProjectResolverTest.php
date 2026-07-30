<?php

namespace App\Tests\Application\Project;

use App\Identity\Entity\User;
use App\Project\Service\OwnedProjectResolver;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class OwnedProjectResolverTest extends ApiTestCase
{
    /**
     * The owner check compares two Uuid value objects. Once the entity manager has been
     * cleared the security token holds a different instance than the one reachable from
     * the project, so the comparison has to be by value — identity would deny the owner.
     */
    public function testOwnerIsResolvedWhenTheUserInstanceIsNotIdentityMapped(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $projectId = $project->getId()->toRfc4122();

        // Load the token's User, then detach it, so the resolver's own lookup hydrates a
        // second User instance carrying a second — equal but not identical — Uuid object.
        $this->entityManager()->clear();
        $tokenUser = $this->entityManager()->find(User::class, $user->getId());
        self::assertNotNull($tokenUser);
        $this->entityManager()->clear();

        $this->authenticate($tokenUser);

        $resolved = $this->resolver()->resolve($projectId);

        self::assertSame($projectId, $resolved->getId()->toRfc4122());
        self::assertNotSame($tokenUser->getId(), $resolved->getUser()->getId(), 'The test needs two distinct Uuid instances to be meaningful.');
    }

    public function testForeignProjectIsDenied(): void
    {
        $project = ProjectFactory::createOne();

        $this->authenticate(UserFactory::createOne());

        $this->expectException(AccessDeniedException::class);
        $this->resolver()->resolve($project->getId()->toRfc4122());
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $this->authenticate(UserFactory::createOne());

        $this->expectException(NotFoundHttpException::class);
        $this->resolver()->resolve(Uuid::v7()->toRfc4122());
    }

    public function testMalformedProjectIdIsNotFound(): void
    {
        $this->authenticate(UserFactory::createOne());

        $this->expectException(NotFoundHttpException::class);
        $this->resolver()->resolve('not-a-uuid');
    }

    private function authenticate(User $user): void
    {
        static::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function resolver(): OwnedProjectResolver
    {
        return static::getContainer()->get(OwnedProjectResolver::class);
    }
}
