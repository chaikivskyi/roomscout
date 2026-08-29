<?php

namespace App\Project\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Uid\Uuid;

final class CreateProjectContext implements CommandInterface
{
    public function __construct(
        public readonly Uuid $contextId,
        public readonly Uuid $projectId,
        public readonly Uuid $actorId,
        public readonly string $prompt,
    ) {
    }
}
