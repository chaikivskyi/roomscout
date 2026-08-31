<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectListItemOutput;
use App\Project\Dto\ProjectPage;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectImageUrlResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final class ListProjectsHandler
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectImageVersionRepository $imageVersions,
        private readonly ProjectImageUrlResolver $imageUrls,
    ) {
    }

    public function __invoke(ListProjects $query): ProjectPage
    {
        ['items' => $projects, 'total' => $total] = $this->projects->findPageForUser(
            $query->actorId,
            $query->page,
            $query->limit,
        );

        $projectIds = array_map(static fn (Project $project): Uuid => $project->getId(), $projects);
        $prompts = $this->contexts->findInitialPromptsForProjects($projectIds);
        $imagePaths = $this->imageVersions->findLatestPathsForProjects($projectIds);

        $items = array_map(function (Project $project) use ($prompts, $imagePaths): ProjectListItemOutput {
            $id = (string) $project->getId();

            return new ProjectListItemOutput(
                $id,
                $prompts[$id] ?? null,
                $this->imageUrls->resolve($imagePaths[$id] ?? null),
                $project->getCreatedAt(),
            );
        }, $projects);

        return new ProjectPage($items, $total, $query->page, $query->limit);
    }
}
