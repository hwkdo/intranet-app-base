<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Search;

final class HilfeSearchText
{
    /**
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return [];
        }

        $tokens = [];

        foreach (explode(' ', $normalized) as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $words
     */
    public static function matchesAny(string $token, array $words): bool
    {
        foreach ($words as $word) {
            if ($word === $token) {
                return true;
            }

            $extra = mb_strlen($word) - mb_strlen($token);

            if (mb_strlen($token) >= 5 && $extra > 0 && $extra <= 3 && str_starts_with($word, $token)) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['-', '–', '—', '/'], ' ', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }
}
