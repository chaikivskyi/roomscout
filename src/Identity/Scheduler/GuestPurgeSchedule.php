<?php

namespace App\Identity\Scheduler;

use App\Identity\Command\PurgeGuests;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('identity_guest_purge')]
final class GuestPurgeSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron(
                    '30 3 * * *',
                    new Envelope(new PurgeGuests(), [new BusNameStamp('command.bus')]),
                    new \DateTimeZone('UTC'),
                ),
            )
            ->processOnlyLastMissedRun(true);
    }
}
