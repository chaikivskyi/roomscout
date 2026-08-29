<?php

namespace App\Api\Bus;

interface CommandBusInterface
{
    public function dispatch(CommandInterface $command): void;
}
