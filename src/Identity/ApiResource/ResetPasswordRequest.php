<?php

namespace App\Identity\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Identity\State\ResetPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/reset-password',
            status: 204,
            openapi: new Operation(
                tags: ['Identity / Account'],
                summary: 'Set a new password using a reset token',
                description: 'Consumes the single-use token from the password reset email. Returns 422 if the token is invalid or expired.',
            ),
            output: false,
            processor: ResetPasswordProcessor::class,
        ),
    ],
)]
final class ResetPasswordRequest
{
    #[Assert\NotBlank]
    public string $token = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    public string $password = '';
}
