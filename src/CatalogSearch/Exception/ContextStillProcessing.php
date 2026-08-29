<?php

namespace App\CatalogSearch\Exception;

use App\Api\Exception\DomainExceptionInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[WithLogLevel(LogLevel::INFO)]
final class ContextStillProcessing extends \RuntimeException implements DomainExceptionInterface, HttpExceptionInterface
{
    private const RETRY_AFTER_SECONDS = 5;

    public function __construct()
    {
        parent::__construct('Matching for this context is still running; retry shortly.');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_ACCEPTED;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return ['Retry-After' => (string) self::RETRY_AFTER_SECONDS];
    }
}
