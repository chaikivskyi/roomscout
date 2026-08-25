<?php

namespace App\CatalogScraper\Service;

use App\CatalogScraper\Exception\ResponseTooLargeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CappedResponseReader
{
    public function __construct(
        private readonly HttpClientInterface $scraperClient,
    ) {
    }

    /**
     * @throws ResponseTooLargeException
     */
    public function read(ResponseInterface $response, int $maxBytes): string
    {
        $body = '';

        foreach ($this->scraperClient->stream($response) as $chunk) {
            $body .= $chunk->getContent();

            if (strlen($body) > $maxBytes) {
                $response->cancel();

                throw new ResponseTooLargeException(sprintf('Response exceeds the maximum size of %d bytes.', $maxBytes));
            }
        }

        return $body;
    }
}
