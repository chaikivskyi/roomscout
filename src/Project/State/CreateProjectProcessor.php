<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Entity\User;
use App\Project\ApiResource\ProjectOutput;
use App\Project\ApiResource\ProjectRequest;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectContext;
use App\Project\Service\ProjectImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<ProjectRequest, ProjectOutput>
 */
final class CreateProjectProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ProjectImageStorage $imageStorage,
        private readonly EntityManagerInterface $entityManager,
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
            $project = new Project($user, $imagePath);
            $projectContext = new ProjectContext($project, $data->prompt);
            $this->entityManager->persist($project);
            $this->entityManager->persist($projectContext);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->imageStorage->remove($imagePath);

            throw $e;
        }

        return new ProjectOutput(
            (string) $project->getId(),
            $projectContext->getPrompt(),
            $projectContext->getStatus()->value,
            $project->getCreatedAt(),
        );
    }
}
