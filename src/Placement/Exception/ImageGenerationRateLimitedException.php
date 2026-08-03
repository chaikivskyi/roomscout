<?php

namespace App\Placement\Exception;

final class ImageGenerationRateLimitedException extends ImageGenerationException
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
