<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Einzelne Buchungen (Einnahme/Ausgabe) eines Vereinsjahres. Der Betrag
     * ist immer positiv, die Richtung ergibt sich aus "type".
     */
    public function up(): void
    {
        Schema::create('tb_cash_book_entries', function (Blueprint $table) {
            $table->bigIncrements('cashBookEntryID');

            $table->unsignedBigInteger('cashBookYearID');
            $table->foreign('cashBookYearID')
                ->references('cashBookYearID')
                ->on('tb_cash_book_years')
                ->cascadeOnDelete();

            $table->unsignedInteger('receipt_number');
            $table->date('date');
            $table->string('type', 20);
            $table->decimal('amount', 10, 2);
            $table->string('description', 255);
            $table->string('category', 100)->nullable();
            $table->text('note')->nullable();

            $table->integer('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')
                ->on('tb_user')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['cashBookYearID', 'receipt_number']);
            $table->index(['cashBookYearID', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_cash_book_entries');
    }
};
