<?php

namespace App\Support\Geo;

final class Haversine
{
    public static function distanceMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    public static function isWithinRadius(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $radiusMeters = 200,
    ): bool {
        return self::distanceMeters($lat1, $lon1, $lat2, $lon2) <= $radiusMeters;
    }
}
