<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AttendanceReviewStatus: string
{
    case Ok = 'ok';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::NeedsReview => 'Needs review',
            self::Approved => 'Approved',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
