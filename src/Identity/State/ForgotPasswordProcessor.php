<?php

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Identity\ApiResource\ForgotPasswordRequest;
use App\Identity\Command\RequestPasswordReset;

/**
 * @implements ProcessorInterface<ForgotPasswordRequest, void>
 */
final class ForgotPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $this->commandBus->dispatch(new RequestPasswordReset($data->email));
    }
}
