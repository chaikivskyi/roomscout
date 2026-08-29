<?php

namespace App\Api\Bus;

use App\Api\Exception\DomainExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

trait UnwrapsHandlerFailure
{
    private function unwrapHandlerFailure(HandlerFailedException $exception): \Throwable
    {
        foreach ($exception->getWrappedExceptions(DomainExceptionInterface::class, true) as $domainException) {
            return $domainException;
        }

        return $exception;
    }
}
