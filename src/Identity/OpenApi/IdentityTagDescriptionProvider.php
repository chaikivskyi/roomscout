<?php

namespace App\Identity\OpenApi;

use App\Api\OpenApi\TagDescriptionProviderInterface;

final class IdentityTagDescriptionProvider implements TagDescriptionProviderInterface
{
    public function getTagDescriptions(): array
    {
        return [
            'Identity / Account' => 'Registration (returns a JWT — signing up logs you in), login/logout, and password recovery. Authenticate with "Authorization: Bearer <token>".',
            'Identity / Users' => 'User profiles.',
        ];
    }
}
