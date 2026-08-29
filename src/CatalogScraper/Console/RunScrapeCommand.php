<?php

namespace App\CatalogScraper\Console;

use App\Api\Bus\CommandBusInterface;
use App\CatalogScraper\Command\RunScrape;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'catalog-scraper:run',
    description: 'Run the catalog scraper now.',
)]
final class RunScrapeCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Dispatching RunScrape…');
        $this->commandBus->dispatch(new RunScrape());
        $io->success('RunScrape dispatched.');

        return Command::SUCCESS;
    }
}
