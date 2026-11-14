<?php

namespace App\Data;

use Illuminate\Contracts\Session\Session;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FlashData extends Data
{
    /**
     * @param  array{type: string, message: string}|null  $toast
     */
    public function __construct(
        public ?string $success = null,
        public ?string $error = null,
        public ?string $info = null,
        public ?string $warning = null,
        public ?array $toast = null,
    ) {}

    public static function fromSession(Session $session): self
    {
        return new self(
            success: $session->get('success'),
            error: $session->get('error'),
            info: $session->get('info'),
            warning: $session->get('warning'),
            toast: $session->get('toast'),
        );
    }
}
