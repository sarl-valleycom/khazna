<?php

namespace App\Attendance;

use App\Models\CorrectionRequest;

final class CorrectionResult
{
    public function __construct(
        public readonly bool $applied,
        public readonly CorrectionRequest $request,
    ) {}
}
