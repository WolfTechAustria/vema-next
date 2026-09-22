<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_invoice_items', function (Blueprint $table) {
            $table->bigIncrements('invoiceItemID');

            $table->unsignedBigInteger('invoiceID');
            $table->foreign('invoiceID')
                ->references('invoiceID')
                ->on('tb_invoices')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('articleID')->nullable();
            $table->foreign('articleID')
                ->references('articleID')
                ->on('tb_articles')
                ->nullOnDelete();

            /*
             * Beschreibung/Preis/USt werden zum Rechnungszeitpunkt aus dem
             * Artikel kopiert (Snapshot) — spätere Preisänderungen am Artikel
             * wirken sich nicht rückwirkend auf bereits gestellte Rechnungen aus.
             */
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 30)->nullable();
            $table->decimal('price_net', 10, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2);

            $table->decimal('line_total_net', 10, 2)->default(0);
            $table->decimal('line_total_gross', 10, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_invoice_items');
    }
};
