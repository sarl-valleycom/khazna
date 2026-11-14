<?php

namespace App\Data;

final readonly class DueGenerationReport
{
    /**
     * @param  list<int>  $contractIds
     */
    public function __construct(
        public int $created,
        public int $skipped,
        public array $contractIds,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, []);
    }

    public function merge(self $other): self
    {
        return new self(
            created: $this->created + $other->created,
            skipped: $this->skipped + $other->skipped,
            contractIds: array_values(array_unique([...$this->contractIds, ...$other->contractIds])),
        );
    }

    public function contractsTouched(): int
    {
        return count($this->contractIds);
    }

    /**
     * @return array{created: int, skipped: int, contracts_touched: int}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'contracts_touched' => $this->contractsTouched(),
        ];
    }
}
