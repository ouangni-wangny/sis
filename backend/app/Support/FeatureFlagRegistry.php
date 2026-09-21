<?php

namespace App\Support;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

final class FeatureFlagRegistry
{
    private const CACHE_KEY = 'sis.feature_flags.map';

    /** @return array<string, bool> */
    public static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            return FeatureFlag::query()
                ->orderBy('key')
                ->pluck('enabled', 'key')
                ->map(fn ($v) => (bool) $v)
                ->all();
        });
    }

    public static function enabled(string $key, bool $default = true): bool
    {
        $map = self::map();

        return array_key_exists($key, $map) ? (bool) $map[$key] : $default;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
