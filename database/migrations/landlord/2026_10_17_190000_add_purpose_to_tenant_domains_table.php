<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eigene Domains können zur App (Anmeldung, Verwaltung) oder zur
     * öffentlichen Vereinshomepage führen.
     */
    public function up(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->string('purpose', 20)->default('app')->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
