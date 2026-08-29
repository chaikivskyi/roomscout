<?php

namespace App\Project\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Uid\Uuid;

final class CreateProject implements CommandInterface
{
    public function __construct(
        public readonly Uuid $projectId,
        public readonly Uuid $contextId,
        public readonly Uuid $versionId,
        public readonly Uuid $ownerId,
        public readonly string $imagePath,
        public readonly string $prompt,
    ) {
    }
}
