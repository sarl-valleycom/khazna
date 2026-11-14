<?php

namespace App\Support;

use Closure;

/**
 * Request-scoped audit reason. Controllers or actions set it before a change;
 * the Auditable trait reads it while the activity is being written.
 */
class AuditContext
{
    private ?string $reason = null;

    public function setReason(?string $reason): void
    {
        $reason = $reason !== null ? trim($reason) : null;

        $this->reason = $reason === '' ? null : $reason;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function clear(): void
    {
        $this->reason = null;
    }

    /**
     * Run a callback with a temporary reason, restoring the previous one.
     */
    public function withReason(?string $reason, Closure $callback): mixed
    {
        $previous = $this->reason;

        $this->setReason($reason);

        try {
            return $callback();
        } finally {
            $this->reason = $previous;
        }
    }
}
