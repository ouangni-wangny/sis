<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Surcharges DB des réglages config/sis.php.
 */
final class SystemSettingsRegistry
{
    private const CACHE_KEY = 'sis.system_settings.map';

    /**
     * Définition des clés exposées en console (valeur = défaut via config path).
     *
     * @return list<array{key: string, label: string, description: string, type: string, config: string, group: string}>
     */
    public static function schema(): array
    {
        return [
            [
                'key' => 'controle_enforce_site_radius',
                'label' => 'Exiger le rayon GPS au contrôle',
                'description' => 'Si activé, le contrôleur doit être dans le rayon du site.',
                'type' => 'boolean',
                'config' => 'sis.controle_enforce_site_radius',
                'group' => 'Contrôles',
            ],
            [
                'key' => 'controle.recheck_min_minutes',
                'label' => 'Délai mini entre deux contrôles (min)',
                'description' => 'Minutes avant de pouvoir recontrôler le même agent posté.',
                'type' => 'integer',
                'config' => 'sis.controle.recheck_min_minutes',
                'group' => 'Contrôles',
            ],
            [
                'key' => 'controle.heure_tolerance_minutes',
                'label' => 'Tolérance horaire vacation (min)',
                'description' => 'Tolérance autour de heure début/fin de vacation.',
                'type' => 'integer',
                'config' => 'sis.controle.heure_tolerance_minutes',
                'group' => 'Contrôles',
            ],
            [
                'key' => 'vacation.duree_max_heures',
                'label' => 'Durée max vacation (h)',
                'description' => 'Durée maximale autorisée pour une vacation.',
                'type' => 'float',
                'config' => 'sis.vacation.duree_max_heures',
                'group' => 'Vacations',
            ],
            [
                'key' => 'vacation.repos_min_heures',
                'label' => 'Repos mini entre vacations (h)',
                'description' => 'Repos minimum entre deux vacations d’un même agent.',
                'type' => 'float',
                'config' => 'sis.vacation.repos_min_heures',
                'group' => 'Vacations',
            ],
            [
                'key' => 'commercial.affaire_suivie_par',
                'label' => 'Affaire suivie par',
                'description' => 'Mention figée sur les PDF proforma / factures.',
                'type' => 'string',
                'config' => 'sis.commercial.affaire_suivie_par',
                'group' => 'Commercial',
            ],
            [
                'key' => 'commercial.telephone',
                'label' => 'Téléphone commercial',
                'description' => 'Téléphone affiché sur les PDF factures.',
                'type' => 'string',
                'config' => 'sis.commercial.telephone',
                'group' => 'Commercial',
            ],
            [
                'key' => 'commercial.apporteur',
                'label' => 'Apporteur',
                'description' => 'Apporteur d’affaires (PDF), vide si non applicable.',
                'type' => 'string',
                'config' => 'sis.commercial.apporteur',
                'group' => 'Commercial',
            ],
            [
                'key' => 'company.name',
                'label' => 'Raison sociale',
                'description' => 'Nom société sur les PDF.',
                'type' => 'string',
                'config' => 'sis.company.name',
                'group' => 'Société',
            ],
            [
                'key' => 'company.legal',
                'label' => 'Pied de page légal',
                'description' => 'Mentions légales en bas des PDF facture.',
                'type' => 'text',
                'config' => 'sis.company.legal',
                'group' => 'Société',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            return SystemSetting::query()
                ->pluck('value', 'key')
                ->all();
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Applique les surcharges DB sur config('sis.*'). */
    public static function applyToConfig(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            return;
        }

        foreach (self::map() as $key => $value) {
            $path = self::configPathFor($key);
            if ($path) {
                Config::set($path, self::castValue($key, $value));
            }
        }
    }

    public static function configPathFor(string $key): ?string
    {
        foreach (self::schema() as $row) {
            if ($row['key'] === $key) {
                return $row['config'];
            }
        }

        return null;
    }

    public static function castValue(string $key, mixed $value): mixed
    {
        $type = 'string';
        foreach (self::schema() as $row) {
            if ($row['key'] === $key) {
                $type = $row['type'];
                break;
            }
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            default => $value === null ? '' : (string) $value,
        };
    }

    /**
     * Valeurs effectives (défaut config + override DB) pour l’API.
     *
     * @return list<array{key: string, label: string, description: string, type: string, group: string, value: mixed, default: mixed, overridden: bool}>
     */
    public static function resolved(): array
    {
        $overrides = self::map();
        $out = [];

        foreach (self::schema() as $row) {
            $default = config($row['config']);
            $overridden = array_key_exists($row['key'], $overrides);
            $value = $overridden
                ? self::castValue($row['key'], $overrides[$row['key']])
                : $default;

            $out[] = [
                'key' => $row['key'],
                'label' => $row['label'],
                'description' => $row['description'],
                'type' => $row['type'],
                'group' => $row['group'],
                'value' => $value,
                'default' => $default,
                'overridden' => $overridden,
            ];
        }

        return $out;
    }
}
