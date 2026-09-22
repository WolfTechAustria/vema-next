<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_invoices', function (Blueprint $table) {
            $table->bigIncrements('invoiceID');

            /*
             * Bleibt leer, solange die Rechnung ein Entwurf ist — wird erst
             * beim Finalisieren atomar vergeben (siehe InvoiceNumberSequence).
             */
            $table->string('invoice_number', 20)->nullable()->unique();

            $table->unsignedBigInteger('recipientID');
            $table->foreign('recipientID')
                ->references('recipientID')
                ->on('tb_invoice_recipients');

            $table->string('purpose', 200)->nullable();

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->text('intro_text')->nullable();
            $table->text('footer_text')->nullable();

            $table->boolean('small_business_no_vat')->default(false);

            $table->decimal('subtotal_net', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total_gross', 10, 2)->default(0);

            $table->string('status', 20)->default('draft');

            $table->dateTime('paid_at')->nullable();
            $table->dateTime('sent_at')->nullable();

            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255)->nullable();

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
        Schema::dropIfExists('tb_invoices');
    }
};
