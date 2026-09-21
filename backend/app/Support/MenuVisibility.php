<?php

namespace App\Support;

use App\Models\MenuOverride;
use Illuminate\Support\Facades\Cache;

final class MenuVisibility
{
    private const CACHE_KEY = 'sis.menu_overrides.map';

    /** @return array<string, bool> overrides bruts en base */
    public static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            return MenuOverride::query()
                ->orderBy('nav_key')
                ->pluck('visible', 'nav_key')
                ->map(fn ($v) => (bool) $v)
                ->all();
        });
    }

    /**
     * Visibilité effective : override menu + flag module lié.
     * Si le module est off → entrée masquée même si override = visible.
     *
     * @return array<string, bool>
     */
    public static function effectiveMap(): array
    {
        $overrides = self::map();
        $flags = FeatureFlagRegistry::map();
        $effective = $overrides;

        foreach (FeatureFlagCatalog::navFeatureMap() as $navKey => $featureKey) {
            $flagOn = array_key_exists($featureKey, $flags)
                ? (bool) $flags[$featureKey]
                : true;
            $menuOn = array_key_exists($navKey, $overrides)
                ? (bool) $overrides[$navKey]
                : true;
            $effective[$navKey] = $flagOn && $menuOn;
        }

        return $effective;
    }

    public static function isVisible(string $navKey, bool $default = true): bool
    {
        $map = self::effectiveMap();

        return array_key_exists($navKey, $map) ? (bool) $map[$navKey] : $default;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
