<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Project\Entity\ProjectContext;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ProjectContext>
 */
final class ProjectContextItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProjectContext
    {
        $project = $this->projectResolver->resolve($uriVariables['projectId'] ?? null);

        $contextId = $uriVariables['contextId'] ?? null;

        if (!\is_string($contextId) || !Uuid::isValid($contextId)) {
            throw new NotFoundHttpException('Context not found.');
        }

        $projectContext = $this->contexts->findOneForProject($project->getId(), Uuid::fromString($contextId));

        if (null === $projectContext) {
            throw new NotFoundHttpException('Context not found.');
        }

        return $projectContext;
    }
}
