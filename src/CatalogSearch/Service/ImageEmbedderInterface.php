<?php

namespace App\CatalogSearch\Service;

interface ImageEmbedderInterface
{
    /**
     * @return list<float>
     */
    public function embedImage(string $mimeType, string $imageBytes): array;

    /**
     * @return list<float>
     */
    public function embedQuery(string $text, string $mimeType, string $imageBytes): array;

    public function model(): string;
}
