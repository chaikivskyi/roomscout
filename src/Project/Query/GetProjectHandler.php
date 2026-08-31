<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectSummaryOutput;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Project\Service\OwnedProjectResolver;
use App\Project\Service\ProjectImageUrlResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetProjectHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectImageVersionRepository $imageVersions,
        private readonly ProjectImageUrlResolver $imageUrls,
    ) {
    }

    public function __invoke(GetProject $query): ProjectSummaryOutput
    {
        $project = $this->projectResolver->resolve($query->projectId, $query->actorId);
        $latestVersion = $this->imageVersions->findLatestForProject($project->getId());

        return new ProjectSummaryOutput(
            (string) $project->getId(),
            $project->getCreatedAt(),
            $this->imageUrls->resolve($latestVersion?->getImagePath()),
        );
    }
}
