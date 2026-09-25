<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vereinsjahre des Kassabuchs — laufen von Jahreshauptversammlung zu
     * Jahreshauptversammlung und nicht nach Kalenderjahr, daher eigener
     * Zeitraum je Datensatz statt einer Jahreszahl.
     */
    public function up(): void
    {
        Schema::create('tb_cash_book_years', function (Blueprint $table) {
            $table->bigIncrements('cashBookYearID');

            $table->string('name', 50);
            $table->date('start_date');
            $table->date('end_date');

            $table->decimal('opening_balance', 10, 2)->default(0);

            $table->date('general_meeting_date')->nullable();
            $table->decimal('closing_balance', 10, 2)->nullable();
            $table->text('closing_note')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->integer('closed_by')->nullable();
            $table->foreign('closed_by')
                ->references('id')
                ->on('tb_user')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_cash_book_years');
    }
};
