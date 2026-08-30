<?php

namespace App\Tests\Fake;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class GuardedHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $inner,
        private readonly bool $allowed,
        private readonly string $clientId,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->allowed) {
            throw new \LogicException(sprintf('A test tried to reach the network through "%s" (%s %s). Application tests must go through a fake; live calls belong in the integration suite.', $this->clientId, $method, $url));
        }

        return $this->inner->request($method, $url, $options);
    }

    /**
     * @param iterable<array-key, ResponseInterface>|ResponseInterface $responses
     */
    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->inner->stream($responses, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new self($this->inner->withOptions($options), $this->allowed, $this->clientId);
    }
}
