<?php

namespace App\Tests\Application\Identity;

use App\Api\Bus\QueryBusInterface;
use App\Identity\Exception\UserNotFound;
use App\Identity\Query\GetUser;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class GetUserQueryTest extends ApiTestCase
{
    public function testReturnsTheUserWithTheGivenId(): void
    {
        $user = UserFactory::createOne(['email' => 'reader@example.com']);

        $found = $this->queryBus()->ask(new GetUser($user->getId()));

        self::assertTrue($user->getId()->equals($found->getId()));
        self::assertSame('reader@example.com', $found->getUserIdentifier());
    }

    public function testUnknownIdThrowsUserNotFound(): void
    {
        $id = Uuid::v7();

        $this->expectException(UserNotFound::class);
        $this->expectExceptionMessage(sprintf('No user with id "%s" exists.', $id->toRfc4122()));

        $this->queryBus()->ask(new GetUser($id));
    }

    private function queryBus(): QueryBusInterface
    {
        return static::getContainer()->get(QueryBusInterface::class);
    }
}
