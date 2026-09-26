<?php

namespace App\Tests\Application\Project;

use App\Project\Service\ProjectImageStorage;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RelocateImagesTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        parent::tearDown();
    }

    public function testAnImageIsMovedUnderItsOwnerPrefixAndTheRowFollows(): void
    {
        $owner = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $owner]);
        ProjectImageVersionFactory::createOne(['project' => $project, 'imagePath' => 'legacy/image.png']);
        $this->storage()->write('legacy/image.png', 'bytes');

        $tester = $this->commandTester();
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $target = ProjectImageStorage::prefixFor($owner->getId()).'/legacy/image.png';
        self::assertTrue($this->storage()->fileExists($target));
        self::assertFalse($this->storage()->fileExists('legacy/image.png'));
        self::assertSame($target, $this->storedPathFor($project->getId()->toRfc4122()));
    }

    public function testADatabaseFailureAfterTheMovePutsTheFileBack(): void
    {
        $owner = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $owner]);
        ProjectImageVersionFactory::createOne(['project' => $project, 'imagePath' => 'legacy/image.png']);
        $this->storage()->write('legacy/image.png', 'bytes');

        $this->connection()->executeStatement(
            "ALTER TABLE project_image_version ADD CONSTRAINT relocate_images_test_guard CHECK (image_path NOT LIKE '%/legacy/image.png')",
        );

        $tester = $this->commandTester();
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertTrue(
            $this->storage()->fileExists('legacy/image.png'),
            'A failed UPDATE must put the file back where the row still points.',
        );
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(static::$kernel ?? static::bootKernel());

        return new CommandTester($application->find('project:relocate-images'));
    }

    private function storedPathFor(string $projectId): string
    {
        $path = $this->connection()->fetchOne(
            'SELECT image_path FROM project_image_version WHERE project_id = CAST(:id AS uuid)',
            ['id' => $projectId],
        );
        self::assertIsString($path);

        return $path;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }
}
