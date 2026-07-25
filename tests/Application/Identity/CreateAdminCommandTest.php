<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

final class CreateAdminCommandTest extends KernelTestCase
{
    use Factories;

    private function commandTester(): CommandTester
    {
        $application = new Application(static::bootKernel());

        return new CommandTester($application->find('identity:create-admin'));
    }

    public function testCreatesAdminUser(): void
    {
        $tester = $this->commandTester();
        $tester->setInputs(['NewAdminPass1', 'NewAdminPass1']);

        $exitCode = $tester->execute(['email' => 'fresh-admin@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('created', $tester->getDisplay());

        $user = static::getContainer()->get('doctrine')->getRepository(User::class)
            ->findOneBy(['email' => 'fresh-admin@example.com']);
        self::assertNotNull($user);
        self::assertContains(Role::Admin->value, $user->getRoles());
        self::assertNotSame('NewAdminPass1', $user->getPassword());
    }

    public function testMismatchedPasswordsFail(): void
    {
        $tester = $this->commandTester();
        $tester->setInputs(['NewAdminPass1', 'SomethingElse1']);

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'fresh-admin@example.com']));
        self::assertStringContainsString('do not match', $tester->getDisplay());
    }

    public function testExistingEmailFailsWithPromoteHint(): void
    {
        // Boot the kernel via the tester first so the factory shares its container.
        $tester = $this->commandTester();
        UserFactory::createOne(['email' => 'existing@example.com']);

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'existing@example.com']));
        self::assertStringContainsString('--promote', $tester->getDisplay());
    }

    public function testPromoteGrantsAdminToExistingUser(): void
    {
        $tester = $this->commandTester();
        UserFactory::createOne(['email' => 'promoted@example.com']);

        $exitCode = $tester->execute(['email' => 'promoted@example.com', '--promote' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $user = static::getContainer()->get('doctrine')->getRepository(User::class)
            ->findOneBy(['email' => 'promoted@example.com']);
        self::assertNotNull($user);
        self::assertContains(Role::Admin->value, $user->getRoles());
    }

    public function testPromoteUnknownEmailFails(): void
    {
        $tester = $this->commandTester();

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'nobody@example.com', '--promote' => true]));
    }

    public function testInvalidEmailFails(): void
    {
        $tester = $this->commandTester();

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'not-an-email']));
    }
}
