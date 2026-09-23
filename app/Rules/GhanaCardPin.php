<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Ghana Card personal identification number, GHA-XXXXXXXXX-X.
 *
 * Nine digits and a final check character. The checksum algorithm is not
 * published, so this validates the shape only — claiming to verify a number we
 * cannot actually verify would be worse than not checking.
 */
class GhanaCardPin implements ValidationRule
{
    public const PATTERN = '/^GHA-\d{9}-\d$/';

    /**
     * Put a typed PIN into canonical form: upper case, no spaces, and the
     * prefix and hyphens supplied if the user left them out.
     */
    public static function normalise(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', (string) $value));

        if ($value === '') {
            return null;
        }

        // Accept a bare ten-digit entry, which is how it is read off the card.
        if (preg_match('/^\d{10}$/', $value) === 1) {
            return 'GHA-'.substr($value, 0, 9).'-'.substr($value, 9);
        }

        return $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match(self::PATTERN, (string) $value) !== 1) {
            $fail('The :attribute must be a Ghana Card PIN in the form GHA-XXXXXXXXX-X.');
        }
    }
}
