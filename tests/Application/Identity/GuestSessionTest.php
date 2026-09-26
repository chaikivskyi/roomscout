<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\User;
use App\Tests\Application\ApiTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;

final class GuestSessionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        $pool = static::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    public function testAnyoneCanStartAGuestSession(): void
    {
        $response = static::createClient()->request('POST', '/api/guest');

        self::assertResponseStatusCodeSame(201);

        $data = $response->toArray();
        self::assertIsString($data['id']);
        self::assertTrue(Uuid::isValid($data['id']));
        self::assertNotEmpty($data['token']);
        self::assertArrayNotHasKey('email', $data);
        self::assertArrayNotHasKey('password', $data);

        $guest = $this->entityManager()->find(User::class, Uuid::fromString($data['id']));
        self::assertNotNull($guest);
        self::assertTrue($guest->isGuest());
        self::assertNull($guest->getEmail());
        self::assertNull($guest->getPassword());
    }

    public function testEachCallMintsADistinctGuest(): void
    {
        $client = static::createClient();

        $first = $client->request('POST', '/api/guest')->toArray();
        $second = $client->request('POST', '/api/guest')->toArray();

        self::assertNotSame($first['id'], $second['id']);
        self::assertNotSame($first['token'], $second['token']);
    }

    public function testGuestSessionsAreRateLimitedPerClient(): void
    {
        $client = static::createClient();

        for ($i = 0; $i < 10; ++$i) {
            $client->request('POST', '/api/guest');
            self::assertResponseStatusCodeSame(201, sprintf('Request %d should still be within the limit.', $i + 1));
        }

        $client->request('POST', '/api/guest');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
    }
}
