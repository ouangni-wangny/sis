<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('vacations.recouvrir', 'web');
        Role::query()->where('guard_name', 'web')->where('name', 'controleur')->first()
            ?->givePermissionTo('vacations.recouvrir');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('guard_name', 'web')->where('name', 'vacations.recouvrir')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
