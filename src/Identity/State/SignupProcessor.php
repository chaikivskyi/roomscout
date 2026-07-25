<?php

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Identity\Api\UserRepositoryInterface;
use App\Identity\ApiResource\SignupInput;
use App\Identity\ApiResource\SignupOutput;
use App\Identity\Entity\User;
use App\Identity\Validator\UniqueUserEmail;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<SignupInput, SignupOutput>
 */
final class SignupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SignupOutput
    {
        $user = new User();
        $user->setEmail($data->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->password));

        try {
            $this->users->save($user);
        } catch (UniqueConstraintViolationException $e) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(new UniqueUserEmail()->message, null, [], $data, 'email', $data->email)]), previous: $e);
        }

        $id = $user->getId() ?? throw new \LogicException('A persisted user must have an id.');

        return new SignupOutput($id, $user->getUserIdentifier(), $this->jwtManager->create($user));
    }
}
