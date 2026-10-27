<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Interne Notizen des Betreibers (z. B. Absprachen, Rechnungsdaten).
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('admin_notes')->nullable();
        });

        // Verlauf der Admin-Aktionen je Verein.
        Schema::create('tenant_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('action', 150);
            $table->json('details')->nullable();
            $table->dateTime('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_events');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('admin_notes');
        });
    }
};
