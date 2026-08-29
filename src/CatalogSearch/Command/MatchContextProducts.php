<?php

namespace App\CatalogSearch\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_matching')]
final class MatchContextProducts implements CommandInterface
{
    public function __construct(
        public readonly string $contextId,
    ) {
    }
}
