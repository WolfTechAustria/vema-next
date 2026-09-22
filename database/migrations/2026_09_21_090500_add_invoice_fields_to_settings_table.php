<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->string('bank_name', 150)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('bic', 20)->nullable();
            $table->string('vat_id', 50)->nullable();

            $table->boolean('small_business_default')->default(true);
            $table->decimal('invoice_default_tax_rate', 5, 2)->default(20.00);
            $table->text('invoice_footer_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name',
                'iban',
                'bic',
                'vat_id',
                'small_business_default',
                'invoice_default_tax_rate',
                'invoice_footer_text',
            ]);
        });
    }
};
