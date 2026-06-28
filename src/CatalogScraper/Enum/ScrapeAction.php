<?php

namespace App\CatalogScraper\Enum;

enum ScrapeAction: string
{
    case Click = 'click';
    case ReadText = 'readText';
    case ReadAttribute = 'readAttribute';
    case WaitFor = 'waitFor';

    public function label(): string
    {
        return match ($this) {
            self::Click => 'Click',
            self::ReadText => 'Read text',
            self::ReadAttribute => 'Read attribute',
            self::WaitFor => 'Wait for',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }

    /**
     * Backing values, e.g. for an Assert\Choice constraint.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
