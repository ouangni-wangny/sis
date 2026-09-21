<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Type agent : rondier → controleur
        DB::table('agents')->where('type', 'rondier')->update(['type' => 'controleur']);
        DB::table('grades')->where('type_agent', 'rondier')->update(['type_agent' => 'controleur']);

        // Grade libellé démo / seed
        DB::table('grades')
            ->where('libelle', 'Rondier')
            ->update([
                'libelle' => 'Contrôleur',
                'description' => 'Contrôleur terrain / contrôles de présence',
            ]);

        // Rôle Spatie : rondier → controleur
        $legacy = Role::query()->where('name', 'rondier')->where('guard_name', 'web')->first();
        if ($legacy) {
            $target = Role::findOrCreate('controleur', 'web');
            foreach ($legacy->users()->get() as $user) {
                $user->removeRole($legacy);
                if (! $user->hasRole($target)) {
                    $user->assignRole($target);
                }
            }
            // Reprendre les permissions du rôle legacy
            $target->syncPermissions($legacy->permissions);
            $legacy->delete();
        } else {
            Role::findOrCreate('controleur', 'web');
        }

        Role::findOrCreate('administration', 'web');
    }

    public function down(): void
    {
        DB::table('agents')->where('type', 'controleur')->update(['type' => 'rondier']);
        DB::table('grades')->where('type_agent', 'controleur')->update(['type_agent' => 'rondier']);

        DB::table('grades')
            ->where('libelle', 'Contrôleur')
            ->update([
                'libelle' => 'Rondier',
                'description' => 'Agent mobile / contrôles',
            ]);

        $legacy = Role::query()->where('name', 'controleur')->where('guard_name', 'web')->first();
        if ($legacy) {
            $target = Role::findOrCreate('rondier', 'web');
            foreach ($legacy->users()->get() as $user) {
                $user->removeRole($legacy);
                if (! $user->hasRole($target)) {
                    $user->assignRole($target);
                }
            }
            $target->syncPermissions($legacy->permissions);
            $legacy->delete();
        }
    }
};
