<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Ein Zähler pro Jahr statt eines MAX()-Scans über tb_invoices:
         * stornierte oder (theoretisch) gelöschte Rechnungen verfälschen so
         * die fortlaufende Nummerierung nicht. Die Zeile wird beim
         * Finalisieren mit lockForUpdate() gesperrt, um Race Conditions bei
         * gleichzeitiger Vergabe zu vermeiden.
         */
        Schema::create('tb_invoice_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_invoice_number_sequences');
    }
};
