<?php

namespace App\CatalogScraper\Command;

use App\CatalogScraper\Message\RunScrapeMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'catalog-scraper:run',
    description: 'Run the catalog scraper now.',
)]
final class RunScrapeCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Dispatching RunScrapeMessage…');
        $this->messageBus->dispatch(new RunScrapeMessage());
        $io->success('RunScrapeMessage dispatched.');

        return Command::SUCCESS;
    }
}
