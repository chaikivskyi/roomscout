<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Project\Entity\ProjectContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<ProjectContext, null>
 */
final class DeleteProjectContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
