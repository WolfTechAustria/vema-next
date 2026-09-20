<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        /*
         * Bootstrap: ohne mindestens einen Admin könnte die neue
         * Benutzerverwaltung von niemandem bedient werden. Weitere Admins
         * werden danach über die Benutzerverwaltung selbst vergeben.
         */
        $userID = DB::table('tb_user')
            ->where('email', 'wo@wolftech.at')
            ->value('id');

        if ($userID) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $role->id,
                'model_type' => \App\Models\User::class,
                'model_id' => $userID,
            ]);
        }
    }

    public function down(): void
    {
        $role = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        if ($role) {
            DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', \App\Models\User::class)
                ->delete();

            $role->delete();
        }
    }
};
