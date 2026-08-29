<?php

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Api\Bus\CommandBusInterface;
use App\Api\Bus\QueryBusInterface;
use App\Identity\ApiResource\SignupInput;
use App\Identity\ApiResource\SignupOutput;
use App\Identity\Command\RegisterUser;
use App\Identity\Exception\EmailAlreadyRegistered;
use App\Identity\Query\GetUser;
use App\Identity\Validator\UniqueUserEmail;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<SignupInput, SignupOutput>
 */
final class SignupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SignupOutput
    {
        $userId = Uuid::v7();

        try {
            $this->commandBus->dispatch(new RegisterUser($userId, $data->email, $data->password));
        } catch (EmailAlreadyRegistered $e) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(new UniqueUserEmail()->message, null, [], $data, 'email', $data->email)]), previous: $e);
        }

        $user = $this->queryBus->ask(new GetUser($userId));

        return new SignupOutput((string) $user->getId(), $user->getUserIdentifier(), $this->jwtManager->create($user));
    }
}
