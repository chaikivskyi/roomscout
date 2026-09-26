<?php

namespace App\Project\Console;

use App\Project\Service\ProjectImageStorage;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'project:relocate-images',
    description: 'Move project images uploaded before owner-scoped storage into their owner\'s prefix.',
)]
final class RelocateImagesCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'project.storage')]
        private readonly FilesystemOperator $storage,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would move without touching anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var list<array{id: string, image_path: string, user_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT v.id, v.image_path, p.user_id
                FROM project_image_version v
                JOIN project p ON p.id = v.project_id
                SQL,
        );

        $moved = 0;
        $missing = 0;

        foreach ($rows as $row) {
            $prefix = ProjectImageStorage::prefixFor(Uuid::fromString($row['user_id']));

            if (str_starts_with($row['image_path'], $prefix.'/')) {
                continue;
            }

            $target = $prefix.'/'.$row['image_path'];

            try {
                if (!$this->storage->fileExists($row['image_path'])) {
                    ++$missing;
                    $io->warning(sprintf('%s is recorded but not on disk; leaving the row alone.', $row['image_path']));

                    continue;
                }
            } catch (FilesystemException $e) {
                $io->error(sprintf('Could not inspect %s: %s', $row['image_path'], $e->getMessage()));

                return Command::FAILURE;
            }

            if (!$dryRun) {
                try {
                    $this->storage->move($row['image_path'], $target);
                } catch (FilesystemException $e) {
                    $io->error(sprintf('Could not move %s: %s', $row['image_path'], $e->getMessage()));

                    return Command::FAILURE;
                }

                try {
                    $this->connection->executeStatement(
                        'UPDATE project_image_version SET image_path = :path WHERE id = CAST(:id AS uuid)',
                        ['path' => $target, 'id' => $row['id']],
                    );
                } catch (\Throwable $e) {
                    $io->error(sprintf('Could not record the new path for %s: %s', $row['image_path'], $e->getMessage()));

                    try {
                        $this->storage->move($target, $row['image_path']);
                        $io->writeln(sprintf('Moved %s back; the row is unchanged.', $target));
                    } catch (FilesystemException $moveBack) {
                        $io->error(sprintf(
                            'The file is at %s but the row still points at %s, and it could not be moved back: %s',
                            $target,
                            $row['image_path'],
                            $moveBack->getMessage(),
                        ));
                    }

                    return Command::FAILURE;
                }
            }

            ++$moved;
            $io->writeln(sprintf('%s -> %s', $row['image_path'], $target));
        }

        $io->success(sprintf('%d image(s) %s, %d recorded but missing.', $moved, $dryRun ? 'would move' : 'moved', $missing));

        return Command::SUCCESS;
    }
}
