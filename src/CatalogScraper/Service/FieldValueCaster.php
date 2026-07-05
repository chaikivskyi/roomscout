<?php

namespace App\CatalogScraper\Service;

use App\CatalogScraper\Enum\ProductField;

class FieldValueCaster
{
    public function cast(ProductField $field, string $raw): string|float|null
    {
        $raw = trim($this->ensureUtf8($raw));

        if (empty($raw)) {
            return null;
        }

        return match ($field->castType()) {
            'float' => (float) $raw,
            default => $raw,
        };
    }

    private function ensureUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
