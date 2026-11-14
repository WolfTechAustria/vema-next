<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lastschrift-Läufe (eine SEPA-XML-Datei je Lauf) und ihre Posten.
     */
    public function up(): void
    {
        Schema::create('tb_sepa_debit_runs', function (Blueprint $table) {
            $table->id('runID');
            $table->integer('yearID')->index();
            $table->date('collection_date');
            $table->string('message_id', 35)->unique();
            $table->string('status', 20)->default('created');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->string('file_path')->nullable();
            $table->string('pain_version', 20);
            $table->integer('created_by')->nullable();
            $table->integer('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tb_sepa_debit_items', function (Blueprint $table) {
            $table->id('itemID');
            $table->unsignedBigInteger('runID')->index();
            $table->integer('entryID')->index();
            $table->unsignedBigInteger('mandateID');
            $table->decimal('amount', 10, 2);
            $table->string('end_to_end_id', 35);
            $table->string('sequence_type', 4);
            $table->timestamp('returned_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_sepa_debit_items');
        Schema::dropIfExists('tb_sepa_debit_runs');
    }
};
