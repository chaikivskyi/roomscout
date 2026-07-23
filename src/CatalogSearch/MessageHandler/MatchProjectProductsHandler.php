<?php

namespace App\CatalogSearch\MessageHandler;

use App\CatalogSearch\Exception\EmbeddingRateLimitedException;
use App\CatalogSearch\Exception\EmbeddingRejectedException;
use App\CatalogSearch\Message\MatchProjectProductsMessage;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\ProjectEmbeddingProvider;
use App\CatalogSearch\Service\ProjectProductMatcher;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class MatchProjectProductsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ProjectEmbeddingProvider $embeddingProvider,
        private readonly ProjectProductMatcher $matcher,
        private readonly LoggerInterface $logger,
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

        try {
            $embedding = $this->embeddingProvider->provide($project);
        } catch (EmbeddingRateLimitedException $e) {
            throw new RecoverableMessageHandlingException($e->getMessage(), previous: $e, retryDelay: $e->getRetryDelayMs(), forceRetry: false);
        } catch (EmbeddingRejectedException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }

        if (null === $embedding) {
            $project->markFailed();
            $this->entityManager->flush();

            return;
        }

        $this->matcher->match($project, $embedding);
    }
}
