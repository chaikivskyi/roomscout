<?php

namespace App\CatalogSearch\MessageHandler;

use App\CatalogSearch\Message\MatchProjectProductsMessage;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\ImageEmbedderInterface;
use App\Project\Entity\Project;
use App\Project\Service\ProjectImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Pgvector\Vector;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class MatchProjectProductsHandler
{
    private const MAX_MATCHES = 1000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ImageEmbedderInterface $embedder,
        private readonly ProjectImageStorage $projectImages,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(float:CATALOG_SEARCH_MIN_MATCH_SCORE)%')]
        private readonly float $minMatchScore,
    ) {
    }

    public function __invoke(MatchProjectProductsMessage $message): void
    {
        $project = $this->entityManager->find(Project::class, Uuid::fromString($message->projectId));

        if (null === $project) {
            throw new RecoverableMessageHandlingException(sprintf('Project %s not found.', $message->projectId), forceRetry: false);
        }

        if ($this->matches->existsForProject($project->getId())) {
            $project->markCompleted();
            $this->entityManager->flush();
            $this->logger->debug('Skipping matching: project already has matches.', ['projectId' => $message->projectId]);

            return;
        }

        $path = $project->getImagePath();

        if (!$this->projectImages->exists($path)) {
            $project->markFailed();
            $this->entityManager->flush();
            $this->logger->warning('Skipping matching: project image is missing from storage.', [
                'projectId' => $message->projectId,
                'path' => $path,
            ]);

            return;
        }

        $vector = $this->embedder->embedQuery(
            $project->getPrompt(),
            $this->projectImages->mimeType($path),
            $this->projectImages->read($path),
        );

        $insertedCount = (int) $this->entityManager->wrapInTransaction(function () use ($project, $vector): int {
            $count = $this->matches->insertMatchesWithinCosineDistance(
                $project->getId(),
                new Vector($vector),
                1.0 - $this->minMatchScore,
                self::MAX_MATCHES,
                $this->embedder->model(),
                new \DateTimeImmutable(),
            );

            $project->markCompleted();

            return $count;
        });

        if (self::MAX_MATCHES === $insertedCount) {
            $this->logger->warning('Match cap hit; result set truncated.', ['projectId' => $message->projectId, 'cap' => self::MAX_MATCHES]);
        }

        $this->logger->info('Stored product matches for project.', ['projectId' => $message->projectId, 'count' => $insertedCount]);
    }
}
