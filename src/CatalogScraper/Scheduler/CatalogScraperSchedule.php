<?php

namespace App\CatalogScraper\Scheduler;

use App\CatalogScraper\Message\RunScrapeMessage;
use DateTimeZone;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('catalog_scraper')]
final class CatalogScraperSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron('0 0 * * *', new RunScrapeMessage(), new DateTimeZone('UTC')),
            )
            ->processOnlyLastMissedRun(true);
    }
}
