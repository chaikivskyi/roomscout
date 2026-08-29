<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Project\ApiResource\ProjectOutput;
use App\Project\ApiResource\ProjectRequest;
use App\Project\Command\CreateProject;
use App\Project\Query\GetProject;
use App\Project\Service\ProjectImageStorage;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ProjectRequest, ProjectOutput>
 */
final class CreateProjectProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly ProjectImageStorage $imageStorage,
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProjectOutput
    {
        $ownerId = $this->actor->requireCurrentId();
        $image = $data->image ?? throw new UnprocessableEntityHttpException('An image file is required.');

        $imagePath = $this->imageStorage->store($image);
        $projectId = Uuid::v7();

        try {
            $this->commandBus->dispatch(new CreateProject(
                projectId: $projectId,
                contextId: Uuid::v7(),
                versionId: Uuid::v7(),
                ownerId: $ownerId,
                imagePath: $imagePath,
                prompt: $data->prompt,
            ));
        } catch (\Throwable $e) {
            $this->imageStorage->remove($imagePath);

            throw $e;
        }

        return $this->queryBus->ask(new GetProject($projectId, $ownerId));
    }
}
