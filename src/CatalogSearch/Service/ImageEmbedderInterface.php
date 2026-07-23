<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Exception\EmbeddingException;

interface ImageEmbedderInterface
{
    /**
     * @return list<float>
     *
     * @throws EmbeddingException
     */
    public function embedImage(string $mimeType, string $imageBytes): array;

    /**
     * @return list<float>
     *
     * @throws EmbeddingException
     */
    public function embedQuery(string $text, string $mimeType, string $imageBytes): array;

    public function model(): string;
}
