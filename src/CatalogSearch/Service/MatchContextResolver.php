<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Exception\ContextStillProcessing;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Repository\ProjectContextRepository;
use Symfony\Component\Uid\Uuid;

final class MatchContextResolver
{
    public function __construct(
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function resolve(Uuid $contextId): ProjectContext
    {
        $context = $this->contexts->find($contextId) ?? throw new ProjectContextNotFound();

        if (ProjectContextStatus::Processing === $context->getStatus()) {
            throw new ContextStillProcessing();
        }

        return $context;
    }
}
