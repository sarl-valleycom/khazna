<?php

namespace App\Support\Metrics;

use App\Support\Period;

/**
 * Base class for metrics aggregations. Filled in phase 2.
 */
abstract class Metrics
{
    public function __construct(
        public readonly Period $period,
    ) {}
}
