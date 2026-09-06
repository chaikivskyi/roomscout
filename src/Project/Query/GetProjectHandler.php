<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectSummaryOutput;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Project\Service\ProjectFinder;
use App\Project\Service\ProjectImageUrlResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetProjectHandler
{
    public function __construct(
        private readonly ProjectFinder $projects,
        private readonly ProjectImageVersionRepository $imageVersions,
        private readonly ProjectImageUrlResolver $imageUrls,
    ) {
    }

    public function __invoke(GetProject $query): ProjectSummaryOutput
    {
        $project = $this->projects->find($query->projectId);
        $latestVersion = $this->imageVersions->findLatestForProject($project->getId());

        return new ProjectSummaryOutput(
            (string) $project->getId(),
            $project->getCreatedAt(),
            $this->imageUrls->resolve($latestVersion?->getImagePath()),
        );
    }
}
