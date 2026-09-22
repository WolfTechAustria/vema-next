<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_invoice_recipients', function (Blueprint $table) {
            $table->bigIncrements('recipientID');

            $table->string('company_name', 200)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('surname', 150)->nullable();

            $table->string('street', 150)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Österreich');

            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('vat_id', 50)->nullable();

            $table->text('note')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_invoice_recipients');
    }
};
