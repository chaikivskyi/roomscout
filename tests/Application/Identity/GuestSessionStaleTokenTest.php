<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\User;
use App\Tests\Application\ApiTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;

final class GuestSessionStaleTokenTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        $pool = static::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    public function testAnInvalidTokenDoesNotBlockStartingAFreshGuestSession(): void
    {
        $response = $this->authClient('not.a.valid.jwt')->request('POST', '/api/guest');

        self::assertResponseStatusCodeSame(201);

        $data = $response->toArray();
        self::assertIsString($data['id']);
        self::assertNotNull($this->entityManager()->find(User::class, Uuid::fromString($data['id'])));
    }

    public function testAnInvalidTokenIsStillRejectedOnOtherEndpoints(): void
    {
        $this->authClient('not.a.valid.jwt')->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAPercentEncodedPathStillHasItsAuthorizationHeaderStripped(): void
    {
        $response = $this->authClient('not.a.valid.jwt')->request('POST', '/api/%67uest');

        self::assertResponseStatusCodeSame(201);

        $data = $response->toArray();
        self::assertIsString($data['id']);
        self::assertNotNull($this->entityManager()->find(User::class, Uuid::fromString($data['id'])));
    }
}
