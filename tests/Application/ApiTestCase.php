<?php

namespace App\Tests\Application;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Zenstruck\Foundry\Test\Factories;

abstract class ApiTestCase extends BaseApiTestCase
{
    use Factories;
    use MailerAssertionsTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected function tokenFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    protected function authClient(string $token): Client
    {
        return static::createClient([], ['headers' => ['Authorization' => 'Bearer '.$token]]);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
