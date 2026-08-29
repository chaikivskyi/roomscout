<?php

namespace App\CatalogSearch\Command;

use App\CatalogSearch\Exception\EmbeddingRateLimitedException;
use App\CatalogSearch\Exception\EmbeddingRejectedException;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\ContextEmbeddingProvider;
use App\CatalogSearch\Service\ContextProductMatcher;
use App\Project\Entity\ProjectContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final class MatchContextProductsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ContextEmbeddingProvider $embeddingProvider,
        private readonly ContextProductMatcher $matcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MatchContextProducts $command): void
    {
        $context = $this->entityManager->find(ProjectContext::class, $this->parseContextId($command->contextId));

        if (null === $context) {
            $this->logger->info('Skipping matching: context was deleted.', ['contextId' => $command->contextId]);

            return;
        }

        if ($this->matches->existsForContext($context->getId())) {
            $context->markCompleted();
            $this->entityManager->flush();
            $this->logger->debug('Skipping matching: context already has matches.', ['contextId' => $command->contextId]);

            return;
        }

        try {
            $embedding = $this->embeddingProvider->provide($context);
        } catch (EmbeddingRateLimitedException $e) {
            throw new RecoverableMessageHandlingException($e->getMessage(), previous: $e, retryDelay: $e->getRetryDelayMs(), forceRetry: false);
        } catch (EmbeddingRejectedException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }

        if (null === $embedding) {
            $context->markFailed();
            $this->entityManager->flush();

            return;
        }

        $this->matcher->match($context, $embedding);
    }

    private function parseContextId(string $contextId): Uuid
    {
        try {
            return Uuid::fromString($contextId);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(sprintf('Malformed context id "%s".', $contextId), previous: $e);
        }
    }
}
