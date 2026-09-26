<?php

namespace App\Identity\Repository;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface, PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneById(Uuid $id): ?User
    {
        return $this->find($id);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function loadUserByIdentifier(string $identifier): ?User
    {
        if (Uuid::isValid($identifier)) {
            return $this->find(Uuid::fromString($identifier));
        }

        return $this->findOneBy(['email' => $identifier]);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function updateLastActiveAt(Uuid $id, \DateTimeImmutable $at): void
    {
        $this->createQueryBuilder('u')
            ->update()
            ->set('u.lastActiveAt', ':at')
            ->where('u.id = :id')
            ->setParameter('at', $at, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $id, UuidType::NAME)
            ->getQuery()
            ->execute();
    }

    /**
     * @return list<Uuid>
     */
    public function findStaleGuestIds(\DateTimeImmutable $idleBefore, \DateTimeImmutable $staleBefore, int $limit): array
    {
        /** @var list<array{id: Uuid}> $rows */
        $rows = $this->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.email IS NULL')
            ->andWhere('u.lastActiveAt < :idleBefore')
            ->andWhere('u.lastActiveAt = u.createdAt OR u.lastActiveAt < :staleBefore')
            ->setParameter('idleBefore', $idleBefore)
            ->setParameter('staleBefore', $staleBefore)
            ->orderBy('u.lastActiveAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): Uuid => $row['id'], $rows);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Uuid>
     */
    public function deleteByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<array{id: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'DELETE FROM users WHERE id = ANY(CAST(:ids AS uuid[])) AND email IS NULL RETURNING id',
            ['ids' => sprintf('{%s}', implode(',', array_map(static fn (Uuid $id): string => (string) $id, $ids)))],
        );

        return array_map(static fn (array $row): Uuid => Uuid::fromString($row['id']), $rows);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
