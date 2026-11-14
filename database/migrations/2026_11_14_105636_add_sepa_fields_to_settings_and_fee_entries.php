<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->string('sepa_creditor_id', 35)->nullable();
            $table->string('sepa_cash_book_mode', 20)->default('summary');
            $table->text('sepa_mandate_text')->nullable();
            $table->unsignedTinyInteger('sepa_lead_days')->default(5);
        });

        Schema::table('tb_membership_fee_entries', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn(['sepa_creditor_id', 'sepa_cash_book_mode', 'sepa_mandate_text', 'sepa_lead_days']);
        });

        Schema::table('tb_membership_fee_entries', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
