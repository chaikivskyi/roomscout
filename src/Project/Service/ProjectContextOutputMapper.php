<?php

namespace App\Project\Service;

use App\Project\ApiResource\ProjectContextOutput;
use App\Project\Entity\ProjectContext;

final class ProjectContextOutputMapper
{
    public function map(ProjectContext $context): ProjectContextOutput
    {
        return new ProjectContextOutput(
            (string) $context->getId(),
            $context->getPrompt(),
            $context->getStatus()->value,
            $context->getCreatedAt(),
        );
    }
}
