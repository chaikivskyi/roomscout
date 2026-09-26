<?php

namespace App\Identity\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Api\State\RateLimitProvider;
use App\Identity\State\CreateGuestSessionProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'GuestSession',
    operations: [
        new Post(
            uriTemplate: '/guest',
            status: 201,
            security: "is_granted('PUBLIC_ACCESS')",
            openapi: new Operation(
                tags: ['Identity / Account'],
                summary: 'Start a guest session',
                description: 'Creates an anonymous guest and returns a JWT for it, so a visitor can create and work on a project before registering. Each call mints a new, unrelated guest. The token is the only handle on that guest\'s work — there is no email to recover it with, so persist it client-side. Signing up while sending this token converts the guest into the new account and keeps its projects.',
                responses: [
                    '201' => new Response(description: 'A new guest and its JWT.'),
                ],
            ),
            input: false,
            output: GuestSessionOutput::class,
            read: false,
            deserialize: false,
            validate: false,
            processor: CreateGuestSessionProcessor::class,
            extraProperties: [RateLimitProvider::LIMITER => 'guest_session'],
        ),
    ],
    normalizationContext: ['groups' => ['guest:read']],
)]
final class GuestSessionOutput
{
    public function __construct(
        #[Groups(['guest:read'])]
        public readonly string $id,
        #[Groups(['guest:read'])]
        #[\SensitiveParameter]
        public readonly string $token,
    ) {
    }
}
