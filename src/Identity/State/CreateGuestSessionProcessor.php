<?php

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Api\Bus\QueryBusInterface;
use App\Identity\ApiResource\GuestSessionOutput;
use App\Identity\Command\RegisterGuest;
use App\Identity\Query\GetUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, GuestSessionOutput>
 */
final class CreateGuestSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GuestSessionOutput
    {
        $userId = Uuid::v7();

        $this->commandBus->dispatch(new RegisterGuest($userId));

        $user = $this->queryBus->ask(new GetUser($userId));

        return new GuestSessionOutput((string) $user->getId(), $this->jwtManager->create($user));
    }
}
