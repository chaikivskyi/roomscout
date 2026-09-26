<?php

namespace App\Identity\Console;

use App\Api\Bus\CommandBusInterface;
use App\Identity\Command\PurgeGuests;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'identity:purge-guests',
    description: 'Delete abandoned guest users together with their projects and uploaded images.',
)]
final class PurgeGuestsCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('empty-ttl-hours', null, InputOption::VALUE_REQUIRED, 'Age after which a guest with no projects is purged', '24')
            ->addOption('stale-ttl-days', null, InputOption::VALUE_REQUIRED, 'Project inactivity after which a guest is purged', '30')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Maximum guests examined per run', '500')
            ->addOption('max-batches', null, InputOption::VALUE_REQUIRED, 'Maximum number of batches processed per run', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emptyTtlHours = $this->positiveIntOption($input, 'empty-ttl-hours');
        $staleTtlDays = $this->positiveIntOption($input, 'stale-ttl-days');
        $batch = $this->positiveIntOption($input, 'batch');
        $maxBatches = $this->positiveIntOption($input, 'max-batches');

        if (null === $emptyTtlHours || null === $staleTtlDays || null === $batch || null === $maxBatches) {
            $io->error('--empty-ttl-hours, --stale-ttl-days, --batch and --max-batches must all be positive integers.');

            return Command::FAILURE;
        }

        $this->commandBus->dispatch(new PurgeGuests($emptyTtlHours, $staleTtlDays, $batch, $maxBatches));

        $io->success('Guest purge finished. See the log for details.');

        return Command::SUCCESS;
    }

    private function positiveIntOption(InputInterface $input, string $name): ?int
    {
        $value = filter_var($input->getOption($name), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return false === $value ? null : $value;
    }
}
