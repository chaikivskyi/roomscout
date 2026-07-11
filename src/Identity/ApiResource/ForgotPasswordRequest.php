<?php

namespace App\Identity\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Identity\State\ForgotPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/forgot-password',
            status: 202,
            openapi: new Operation(
                tags: ['Identity / Account'],
                summary: 'Request a password reset email',
                description: 'Always returns 202, whether or not the email belongs to an account, to prevent user enumeration.',
            ),
            output: false,
            processor: ForgotPasswordProcessor::class,
        ),
    ],
)]
final class ForgotPasswordRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';
}
