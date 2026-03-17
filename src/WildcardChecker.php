<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended;

use Illuminate\Support\Collection;

/**
 * Utility for matching permission names with wildcard patterns.
 */
final class WildcardChecker
{
    /**
     * Cache of computed match results.
     *
     * @var array<string, bool>
     */
    private static array $cache = [];

    /**
     * Check whether a permission name matches a wildcard pattern.
     *
     * @param string $pattern
     * @param string $permission
     * @return bool
     */
    public static function matches(string $pattern, string $permission): bool
    {
        if ($pattern === $permission) {
            return true;
        }

        if (! str_contains($pattern, '*')) {
            return false;
        }

        $key = "{$pattern}\0{$permission}";

        return self::$cache[$key] ??= self::regexMatch($pattern, $permission);
    }

    /**
     * Return every item in $names that matches $pattern.
     *
     * @param string $pattern
     * @param Collection<int,string>|array<int,string> $names
     * @return Collection<int,string>
     */
    public static function filter(string $pattern, Collection|array $names): Collection
    {
        $names = $names instanceof Collection ? $names : collect($names);

        if (! str_contains($pattern, '*')) {
            return $names->filter(fn (string $n): bool => $n === $pattern)->values();
        }

        return $names->filter(fn (string $n): bool => self::matches($pattern, $n))->values();
    }

    /**
     * Clear the wildcard match cache.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$cache = [];
    }

    // -----------------------------------------------------------------

    /**
     * Perform a regex-based wildcard match.
     *
     * @param string $pattern
     * @param string $permission
     * @return bool
     */
    private static function regexMatch(string $pattern, string $permission): bool
    {
        $regex = '/^'.str_replace('*', '.*', str_replace('.', '\.', $pattern)).'$/';

        return (bool) preg_match($regex, $permission);
    }
}
