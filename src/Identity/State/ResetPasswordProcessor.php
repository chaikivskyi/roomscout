<?php

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Identity\ApiResource\ResetPasswordRequest;
use App\Identity\Command\ResetPassword;

/**
 * @implements ProcessorInterface<ResetPasswordRequest, void>
 */
final class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $this->commandBus->dispatch(new ResetPassword($data->token, $data->password));
    }
}
