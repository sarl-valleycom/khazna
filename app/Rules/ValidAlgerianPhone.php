<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAlgerianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Phone::normalize($value) === null) {
            $fail(__('Enter a valid Algerian mobile number, for example 0550 12 34 56.'));
        }
    }
}
