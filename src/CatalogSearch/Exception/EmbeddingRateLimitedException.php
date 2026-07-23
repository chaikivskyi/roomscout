<?php

namespace App\CatalogSearch\Exception;

final class EmbeddingRateLimitedException extends EmbeddingException
{
    public function __construct(
        string $message,
        private readonly ?int $retryDelayMs = null,
    ) {
        parent::__construct($message);
    }

    public function getRetryDelayMs(): ?int
    {
        return $this->retryDelayMs;
    }
}
