<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\JournalAudit;
use App\Models\MenuOverride;
use App\Models\SystemSetting;
use App\Support\FeatureFlagCatalog;
use App\Support\FeatureFlagRegistry;
use App\Support\MenuVisibility;
use App\Support\SystemSettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SystemConfigController extends Controller
{
    public function runtimeConfig(): JsonResponse
    {
        return response()->json([
            'data' => [
                'feature_flags' => FeatureFlagRegistry::map(),
                'menu_overrides' => MenuVisibility::effectiveMap(),
            ],
        ]);
    }

    public function featureFlags(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.features.manage'), 403);

        $catalog = collect(FeatureFlagCatalog::flags())->keyBy('key');

        $flags = FeatureFlag::query()
            ->with('updatedBy:id,nom,prenom,email')
            ->orderBy('key')
            ->get()
            ->map(function (FeatureFlag $flag) use ($catalog) {
                $meta = $catalog->get($flag->key);

                return [
                    'id' => $flag->id,
                    'key' => $flag->key,
                    'label' => $flag->label,
                    'description' => $flag->description,
                    'enabled' => (bool) $flag->enabled,
                    'group' => $meta['group'] ?? 'Autre',
                    'critical' => (bool) ($meta['critical'] ?? false),
                    'updated_by' => $flag->updatedBy ? [
                        'id' => $flag->updatedBy->id,
                        'nom' => $flag->updatedBy->nom,
                        'prenom' => $flag->updatedBy->prenom,
                        'email' => $flag->updatedBy->email,
                    ] : null,
                    'updated_at' => $flag->updated_at,
                ];
            });

        return response()->json(['data' => $flags]);
    }

    public function updateFeatureFlag(Request $request, string $key): JsonResponse
    {
        abort_unless($request->user()?->can('system.features.manage'), 403);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $flag = FeatureFlag::query()->where('key', $key)->first();
        if (! $flag) {
            throw ValidationException::withMessages([
                'key' => 'Fonctionnalité inconnue.',
            ]);
        }

        $enabled = (bool) $data['enabled'];

        DB::transaction(function () use ($flag, $enabled, $request) {
            $flag->update([
                'enabled' => $enabled,
                'updated_by' => $request->user()?->id,
            ]);

            // Lien auto menu ↔ module : désactiver = masquer ; réactiver = réafficher
            $userId = $request->user()?->id;
            foreach (FeatureFlagCatalog::navKeysForFeature($flag->key) as $navKey) {
                MenuOverride::query()->updateOrCreate(
                    ['nav_key' => $navKey],
                    [
                        'visible' => $enabled,
                        'updated_by' => $userId,
                    ],
                );
            }
        });

        FeatureFlagRegistry::forget();
        MenuVisibility::forget();

        $flag->refresh();
        $meta = collect(FeatureFlagCatalog::flags())->firstWhere('key', $flag->key);

        return response()->json([
            'data' => [
                'id' => $flag->id,
                'key' => $flag->key,
                'label' => $flag->label,
                'description' => $flag->description,
                'enabled' => (bool) $flag->enabled,
                'group' => $meta['group'] ?? 'Autre',
                'critical' => (bool) ($meta['critical'] ?? false),
                'updated_at' => $flag->updated_at,
            ],
        ]);
    }

    public function updateFeatureFlagGroup(Request $request, string $group): JsonResponse
    {
        abort_unless($request->user()?->can('system.features.manage'), 403);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $data['enabled'];
        $keys = collect(FeatureFlagCatalog::flags())
            ->where('group', $group)
            ->pluck('key')
            ->all();

        if ($keys === []) {
            throw ValidationException::withMessages([
                'group' => 'Groupe inconnu.',
            ]);
        }

        DB::transaction(function () use ($keys, $enabled, $request) {
            $userId = $request->user()?->id;
            foreach (FeatureFlag::query()->whereIn('key', $keys)->get() as $flag) {
                $flag->update([
                    'enabled' => $enabled,
                    'updated_by' => $userId,
                ]);
                foreach (FeatureFlagCatalog::navKeysForFeature($flag->key) as $navKey) {
                    MenuOverride::query()->updateOrCreate(
                        ['nav_key' => $navKey],
                        ['visible' => $enabled, 'updated_by' => $userId],
                    );
                }
            }
        });

        FeatureFlagRegistry::forget();
        MenuVisibility::forget();

        return $this->featureFlags($request);
    }

    public function resetFeatureFlags(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.features.manage'), 403);

        DB::transaction(function () use ($request) {
            $userId = $request->user()?->id;
            foreach (FeatureFlag::query()->get() as $flag) {
                $flag->update([
                    'enabled' => true,
                    'updated_by' => $userId,
                ]);
            }
            foreach (MenuOverride::query()->get() as $menu) {
                $menu->update([
                    'visible' => true,
                    'updated_by' => $userId,
                ]);
            }
        });

        FeatureFlagRegistry::forget();
        MenuVisibility::forget();

        return $this->featureFlags($request);
    }

    public function menuOverrides(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.menu.manage'), 403);

        $items = MenuOverride::query()
            ->orderBy('nav_key')
            ->get()
            ->map(fn (MenuOverride $row) => [
                'id' => $row->id,
                'nav_key' => $row->nav_key,
                'visible' => (bool) $row->visible,
                'updated_at' => $row->updated_at,
            ]);

        return response()->json(['data' => $items]);
    }

    public function syncMenuOverrides(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.menu.manage'), 403);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.nav_key' => ['required', 'string', 'max:100'],
            'items.*.visible' => ['required', 'boolean'],
        ]);

        $userId = $request->user()?->id;

        foreach ($data['items'] as $item) {
            MenuOverride::query()->updateOrCreate(
                ['nav_key' => $item['nav_key']],
                [
                    'visible' => (bool) $item['visible'],
                    'updated_by' => $userId,
                ],
            );
        }

        MenuVisibility::forget();

        $items = MenuOverride::query()
            ->orderBy('nav_key')
            ->get()
            ->map(fn (MenuOverride $row) => [
                'id' => $row->id,
                'nav_key' => $row->nav_key,
                'visible' => (bool) $row->visible,
                'updated_at' => $row->updated_at,
            ]);

        return response()->json(['data' => $items]);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('system.features.manage')
                || $request->user()?->can('system.menu.manage'),
            403,
        );

        $rows = JournalAudit::query()
            ->with('user:id,nom,prenom,email')
            ->whereIn('auditable_type', [
                FeatureFlag::class,
                MenuOverride::class,
                SystemSetting::class,
                'App\\Models\\FeatureFlag',
                'App\\Models\\MenuOverride',
                'App\\Models\\SystemSetting',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (JournalAudit $row) => [
                'id' => $row->id,
                'action' => $row->action,
                'auditable_type' => class_basename((string) $row->auditable_type),
                'auditable_id' => $row->auditable_id,
                'ancien' => $row->ancien,
                'nouveau' => $row->nouveau,
                'created_at' => $row->created_at,
                'user' => $row->user ? [
                    'id' => $row->user->id,
                    'nom' => $row->user->nom,
                    'prenom' => $row->user->prenom,
                    'email' => $row->user->email,
                ] : null,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function settings(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.settings.manage'), 403);

        return response()->json(['data' => SystemSettingsRegistry::resolved()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.settings.manage'), 403);

        $allowedKeys = collect(SystemSettingsRegistry::schema())->pluck('key')->all();

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', Rule::in($allowedKeys)],
            'settings.*.value' => ['nullable'],
        ]);

        $userId = $request->user()?->id;

        foreach ($data['settings'] as $item) {
            $key = $item['key'];
            $value = SystemSettingsRegistry::castValue($key, $item['value'] ?? null);

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_by' => $userId,
                ],
            );
        }

        SystemSettingsRegistry::forget();
        SystemSettingsRegistry::applyToConfig();

        return response()->json(['data' => SystemSettingsRegistry::resolved()]);
    }

    public function resetSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.settings.manage'), 403);

        SystemSetting::query()->delete();
        SystemSettingsRegistry::forget();

        return response()->json(['data' => SystemSettingsRegistry::resolved()]);
    }
}
