<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_invoice_reminders', function (Blueprint $table) {
            $table->bigIncrements('reminderID');

            $table->unsignedBigInteger('invoiceID');
            $table->foreign('invoiceID')
                ->references('invoiceID')
                ->on('tb_invoices')
                ->cascadeOnDelete();

            // 1 = Zahlungserinnerung, 2 = 1. Mahnung, 3 = 2. Mahnung
            $table->unsignedTinyInteger('level');

            // email | post
            $table->string('delivery_method', 20)->default('email');

            $table->string('file_path', 255);
            $table->string('file_name', 255);

            $table->string('sent_to', 150)->nullable();
            $table->dateTime('sent_at');

            $table->integer('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')
                ->on('tb_user')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_invoice_reminders');
    }
};
