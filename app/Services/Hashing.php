<?php

namespace App\Services;

class Hashing
{
    /** Deterministic JSON (recursively sorted keys) so equal content always hashes equally. */
    public static function stable(mixed $v): string
    {
        return json_encode(self::sort($v), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function content(mixed $v): string
    {
        return hash('sha256', self::stable($v));
    }

    private static function sort(mixed $v): mixed
    {
        // 90.0 and 90 must hash identically: JSON columns don't preserve the difference on a round trip.
        if (is_float($v) && is_finite($v) && floor($v) === $v && abs($v) < 1e15) {
            return (int) $v;
        }
        if (! is_array($v)) {
            return $v;
        }
        if (array_is_list($v)) {
            return array_map([self::class, 'sort'], $v);
        }
        ksort($v);

        return array_map([self::class, 'sort'], $v);
    }
}
