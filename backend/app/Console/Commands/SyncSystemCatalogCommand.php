<?php

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use App\Models\MenuOverride;
use App\Support\FeatureFlagCatalog;
use App\Support\FeatureFlagRegistry;
use App\Support\MenuVisibility;
use Illuminate\Console\Command;

class SyncSystemCatalogCommand extends Command
{
    protected $signature = 'system:sync-catalog
                            {--menus : Créer aussi les menu_overrides manquants (visibles)}';

    protected $description = 'Aligne feature_flags (et optionnellement menu_overrides) sur le catalogue code';

    public function handle(): int
    {
        $created = 0;
        $updated = 0;

        foreach (FeatureFlagCatalog::flags() as $flag) {
            $row = FeatureFlag::query()->firstOrNew(['key' => $flag['key']]);
            $isNew = ! $row->exists;
            $row->fill([
                'label' => $flag['label'],
                'description' => $flag['description'],
            ]);
            if ($isNew) {
                $row->enabled = true;
            }
            $row->save();
            $isNew ? $created++ : $updated++;
        }

        FeatureFlagRegistry::forget();
        $this->info("Flags : {$created} créés, {$updated} mis à jour.");

        if ($this->option('menus')) {
            $menuCreated = 0;
            foreach (array_keys(FeatureFlagCatalog::navFeatureMap()) as $navKey) {
                $menu = MenuOverride::query()->firstOrCreate(
                    ['nav_key' => $navKey],
                    ['visible' => true],
                );
                if ($menu->wasRecentlyCreated) {
                    $menuCreated++;
                }
            }
            MenuVisibility::forget();
            $this->info("Menus : {$menuCreated} créés.");
        }

        return self::SUCCESS;
    }
}
