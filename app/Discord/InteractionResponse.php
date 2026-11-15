<?php

namespace App\Discord;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One Discord interaction response. Reply types follow the Discord API:
 * 1 pong, 4 a message, 5 a deferred message, 9 a modal.
 *
 * @implements Arrayable<string, mixed>
 */
final class InteractionResponse implements Arrayable
{
    public const TYPE_PONG = 1;

    public const TYPE_MESSAGE = 4;

    public const TYPE_DEFERRED = 5;

    public const TYPE_MODAL = 9;

    public const EPHEMERAL = 64;

    public const BUTTON_PRIMARY = 1;

    public const BUTTON_SECONDARY = 2;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly int $type,
        public readonly array $data = [],
    ) {}

    public static function pong(): self
    {
        return new self(self::TYPE_PONG);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  list<array<string, mixed>>  $embeds
     */
    public static function message(
        string $content,
        bool $ephemeral = false,
        array $components = [],
        array $embeds = [],
    ): self {
        $data = ['content' => $content];

        if ($ephemeral) {
            $data['flags'] = self::EPHEMERAL;
        }

        if ($components !== []) {
            $data['components'] = $components;
        }

        if ($embeds !== []) {
            $data['embeds'] = $embeds;
        }

        return new self(self::TYPE_MESSAGE, $data);
    }

    public static function deferred(bool $ephemeral = false): self
    {
        return new self(self::TYPE_DEFERRED, $ephemeral ? ['flags' => self::EPHEMERAL] : []);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    public static function modal(string $customId, string $title, array $components): self
    {
        return new self(self::TYPE_MODAL, [
            'custom_id' => $customId,
            'title' => mb_substr($title, 0, 45),
            'components' => $components,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    public static function actionRow(array $components): array
    {
        return ['type' => 1, 'components' => $components];
    }

    /**
     * @return array<string, mixed>
     */
    public static function button(string $label, string $customId, int $style = self::BUTTON_PRIMARY): array
    {
        return [
            'type' => 2,
            'style' => $style,
            'label' => mb_substr($label, 0, 80),
            'custom_id' => $customId,
        ];
    }

    /**
     * A modal text input. Style 2 is a paragraph, which suits reports.
     *
     * @return array<string, mixed>
     */
    public static function textInput(
        string $customId,
        string $label,
        bool $required = true,
        int $style = 2,
        int $maxLength = 4000,
    ): array {
        return [
            'type' => 4,
            'custom_id' => $customId,
            'label' => mb_substr($label, 0, 45),
            'style' => $style,
            'required' => $required,
            'max_length' => min(4000, $maxLength),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data === []
            ? ['type' => $this->type]
            : ['type' => $this->type, 'data' => $this->data];
    }
}
