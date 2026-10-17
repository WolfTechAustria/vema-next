<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate([
            'name' => 'homepage',
            'guard_name' => 'web',
        ]);
    }

    public function down(): void
    {
        $role = Role::where('name', 'homepage')->where('guard_name', 'web')->first();

        if ($role) {
            DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', User::class)
                ->delete();

            $role->delete();
        }
    }
};
