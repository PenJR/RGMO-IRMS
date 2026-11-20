<?php

namespace App\Support;

final class DecimalFormatter
{
    /** Format captured decimal strings without converting large monetary values to floats. */
    public static function format(int|float|string|null $value): string
    {
        if ($value === null) {
            return 'Not recorded';
        }
        if (preg_match('/\A(-?)(\d+)(?:\.(\d{1,2}))?\z/', (string) $value, $parts)) {
            return $parts[1].preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[2])
                .'.'.str_pad($parts[3] ?? '', 2, '0');
        }

        return number_format((float) $value, 2);
    }
}
