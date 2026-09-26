<?php

namespace App\Tests\Application\Identity;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

final class PurgeGuestsCommandTest extends KernelTestCase
{
    use Factories;

    private function commandTester(): CommandTester
    {
        $application = new Application(static::bootKernel());

        return new CommandTester($application->find('identity:purge-guests'));
    }

    public function testANegativeTtlIsRejectedRatherThanInvertingTheWindow(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute(['--empty-ttl-hours' => '-1']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('positive', $tester->getDisplay());
    }

    public function testANegativeMaxBatchesIsRejectedRatherThanRunningUnbounded(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute(['--max-batches' => '-1']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('positive', $tester->getDisplay());
    }

    public function testAValidRunPurgesAndReportsSuccess(): void
    {
        $mintedAt = new \DateTimeImmutable('-40 days');

        for ($i = 0; $i < 3; ++$i) {
            UserFactory::new()->guest()->create(['createdAt' => $mintedAt, 'lastActiveAt' => $mintedAt]);
        }

        $tester = $this->commandTester();
        $exitCode = $tester->execute(['--batch' => '2']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Guest purge finished.', $tester->getDisplay());
        self::assertSame(0, UserFactory::count());
    }
}
