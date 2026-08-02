<?php

namespace App\CatalogSearch\Service;

use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class MatchContextResolver
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function resolve(mixed $projectId, mixed $contextId): ProjectContext
    {
        $project = $this->projectResolver->resolve($projectId);

        if (!\is_string($contextId) || !Uuid::isValid($contextId)) {
            throw new NotFoundHttpException('Context not found.');
        }

        $projectContext = $this->contexts->findOneForProject($project->getId(), Uuid::fromString($contextId));

        if (null === $projectContext) {
            throw new NotFoundHttpException('Context not found.');
        }

        return $projectContext;
    }

    public function processingResponse(ProjectContext $projectContext): ?JsonResponse
    {
        if (ProjectContextStatus::Processing !== $projectContext->getStatus()) {
            return null;
        }

        return new JsonResponse(
            ['status' => ProjectContextStatus::Processing->value],
            Response::HTTP_ACCEPTED,
            ['Retry-After' => '5'],
        );
    }
}
