<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** La RH consulte les vacations et la feuille de pointage (fiche agent, paie). */
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('vacations.view', 'web');
        Role::query()->where('guard_name', 'web')->where('name', 'rh')->first()
            ?->givePermissionTo('vacations.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('guard_name', 'web')->where('name', 'rh')->first()
            ?->revokePermissionTo('vacations.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
