<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Entity\User;
use App\Project\ApiResource\ProjectOutput;
use App\Project\ApiResource\ProjectRequest;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectImageStorage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

/**
 * @implements ProcessorInterface<ProjectRequest, ProjectOutput>
 */
final class CreateProjectProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ProjectImageStorage $imageStorage,
        private readonly ProjectRepository $projects,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProjectOutput
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        $image = $data->image ?? throw new UnprocessableEntityHttpException('An image file is required.');

        $imagePath = $this->imageStorage->store($image);

        try {
            $project = new Project($user, $imagePath, $data->prompt);
            $this->projects->save($project);
        } catch (Throwable $e) {
            $this->imageStorage->remove($imagePath);

            throw $e;
        }

        return new ProjectOutput(
            $project->getId()->toRfc4122(),
            $project->getPrompt(),
            $project->getStatus()->value,
            $project->getCreatedAt(),
        );
    }
}
