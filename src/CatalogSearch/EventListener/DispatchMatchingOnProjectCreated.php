<?php

namespace App\CatalogSearch\EventListener;

use App\CatalogSearch\Message\MatchProjectProductsMessage;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Project::class)]
final class DispatchMatchingOnProjectCreated
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function postPersist(Project $project): void
    {
        $this->messageBus->dispatch(new MatchProjectProductsMessage($project->getId()->toRfc4122()));
    }
}
