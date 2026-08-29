<?php

namespace App\CatalogSearch\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_embeddings')]
final class EmbedProductThumbnail implements CommandInterface
{
    public function __construct(
        public readonly string $productId,
    ) {
    }
}
