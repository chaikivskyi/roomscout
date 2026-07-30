<?php

namespace App\CatalogSearch\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_embeddings')]
final class EmbedProductThumbnailMessage
{
    public function __construct(
        public readonly string $productId,
    ) {
    }
}
