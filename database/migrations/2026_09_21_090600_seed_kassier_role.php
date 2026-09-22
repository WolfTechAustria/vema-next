<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate([
            'name' => 'kassier',
            'guard_name' => 'web',
        ]);
    }

    public function down(): void
    {
        $role = Role::where('name', 'kassier')->where('guard_name', 'web')->first();

        if ($role) {
            DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', \App\Models\User::class)
                ->delete();

            $role->delete();
        }
    }
};
