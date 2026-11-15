<?php

namespace App\Discord;

/**
 * A decoded Discord interaction. Every ID is exposed as a string: Discord
 * snowflakes are 64-bit integers and a JavaScript-sized number would silently
 * change values above 2^53.
 */
final class Interaction
{
    public const TYPE_PING = 1;

    public const TYPE_COMMAND = 2;

    public const TYPE_COMPONENT = 3;

    public const TYPE_MODAL_SUBMIT = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    public function type(): int
    {
        return (int) ($this->payload['type'] ?? 0);
    }

    public function guildId(): ?string
    {
        return $this->stringValue($this->payload['guild_id'] ?? null);
    }

    public function userId(): ?string
    {
        $member = $this->payload['member'] ?? null;
        $user = is_array($member) && isset($member['user']) && is_array($member['user'])
            ? $member['user']
            : ($this->payload['user'] ?? null);

        return is_array($user) ? $this->stringValue($user['id'] ?? null) : null;
    }

    public function commandName(): ?string
    {
        $data = $this->payload['data'] ?? null;

        return is_array($data) && is_string($data['name'] ?? null) ? $data['name'] : null;
    }

    public function customId(): ?string
    {
        $data = $this->payload['data'] ?? null;

        return is_array($data) && is_string($data['custom_id'] ?? null) ? $data['custom_id'] : null;
    }

    public function option(string $name): ?string
    {
        $data = $this->payload['data'] ?? null;

        if (! is_array($data) || ! is_array($data['options'] ?? null)) {
            return null;
        }

        foreach ($data['options'] as $option) {
            if (is_array($option) && ($option['name'] ?? null) === $name) {
                return $this->stringValue($option['value'] ?? null);
            }
        }

        return null;
    }

    /**
     * A modal text input's value, found by its custom_id at any nesting depth.
     */
    public function componentValue(string $customId): ?string
    {
        $data = $this->payload['data'] ?? null;

        if (! is_array($data) || ! is_array($data['components'] ?? null)) {
            return null;
        }

        return $this->findComponentValue($data['components'], $customId);
    }

    /**
     * @param  array<int, mixed>  $components
     */
    private function findComponentValue(array $components, string $customId): ?string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            if (($component['custom_id'] ?? null) === $customId && array_key_exists('value', $component)) {
                return $this->stringValue($component['value']);
            }

            if (is_array($component['components'] ?? null)) {
                $found = $this->findComponentValue($component['components'], $customId);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
