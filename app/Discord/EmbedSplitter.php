<?php

namespace App\Discord;

/**
 * Keeps an embed inside Discord's limits: 1024 characters per field value, 25
 * fields, and 6000 characters in total across a message. A long field is split
 * at line breaks (never mid-line), and the fields are packed into as many
 * embeds as needed. Send each returned embed as its own message, because the
 * 6000 limit counts every embed in one message together.
 */
final class EmbedSplitter
{
    public const FIELD_NAME_LIMIT = 256;

    public const FIELD_VALUE_LIMIT = 1024;

    public const MAX_FIELDS = 25;

    /** Under Discord's 6000, leaving room for the " (part n/m)" title suffix. */
    public const MAX_TOTAL = 5500;

    private const TITLE_SUFFIX_RESERVE = 20;

    /**
     * @param  array<string, mixed>  $embed
     * @return list<array<string, mixed>>
     */
    public static function split(array $embed): array
    {
        $rawFields = is_array($embed['fields'] ?? null) ? $embed['fields'] : [];

        if ($rawFields === []) {
            return [$embed];
        }

        $fields = [];

        foreach ($rawFields as $field) {
            array_push($fields, ...self::splitField($field));
        }

        $footer = $embed['footer'] ?? null;
        $base = mb_strlen((string) ($embed['title'] ?? ''))
            + mb_strlen((string) ($embed['description'] ?? ''))
            + (is_array($footer) ? mb_strlen((string) ($footer['text'] ?? '')) : 0)
            + self::TITLE_SUFFIX_RESERVE;

        $groups = [];
        $current = [];
        $size = $base;

        foreach ($fields as $field) {
            $fieldSize = mb_strlen($field['name']) + mb_strlen($field['value']);

            if ($current !== [] && (count($current) >= self::MAX_FIELDS || $size + $fieldSize > self::MAX_TOTAL)) {
                $groups[] = $current;
                $current = [];
                $size = $base;
            }

            $current[] = $field;
            $size += $fieldSize;
        }

        $groups[] = $current;

        $embeds = [];

        foreach ($groups as $index => $group) {
            $part = $embed;
            $part['fields'] = $group;

            if (count($groups) > 1 && isset($embed['title'])) {
                $part['title'] = $embed['title'].' (part '.($index + 1).'/'.count($groups).')';
            }

            $embeds[] = $part;
        }

        return $embeds;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array{name: string, value: string, inline: bool}>
     */
    private static function splitField(array $field): array
    {
        $name = mb_substr((string) ($field['name'] ?? ''), 0, self::FIELD_NAME_LIMIT - 8);
        $inline = (bool) ($field['inline'] ?? false);
        $chunks = self::chunkLines((string) ($field['value'] ?? ''));

        $result = [];

        foreach ($chunks as $index => $chunk) {
            $result[] = [
                'name' => $index === 0 ? $name : $name.' (cont.)',
                'value' => $chunk,
                'inline' => $inline,
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function chunkLines(string $value): array
    {
        if ($value === '') {
            return ['-'];
        }

        $chunks = [];
        $current = '';

        foreach (explode("\n", $value) as $line) {
            foreach (mb_str_split($line, self::FIELD_VALUE_LIMIT) ?: [''] as $piece) {
                $candidate = $current === '' ? $piece : $current."\n".$piece;

                if (mb_strlen($candidate) > self::FIELD_VALUE_LIMIT) {
                    $chunks[] = $current;
                    $current = $piece;

                    continue;
                }

                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks === [] ? ['-'] : $chunks;
    }
}
