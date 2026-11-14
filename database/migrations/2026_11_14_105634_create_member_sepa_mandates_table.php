<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SEPA-Lastschriftmandate der Mitglieder. Ein neues Mandat widerruft das
     * bisherige; widerrufene bleiben zur Nachvollziehbarkeit erhalten.
     */
    public function up(): void
    {
        Schema::create('tb_member_sepa_mandates', function (Blueprint $table) {
            $table->id('mandateID');
            $table->integer('memberID')->index();
            $table->string('reference', 35)->unique();
            $table->string('account_holder', 70);
            $table->text('iban');
            $table->string('bic', 11)->nullable();
            $table->date('signed_on');
            $table->string('source', 10)->default('manual');
            $table->timestamp('first_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->boolean('revoked_by_member')->default(false);
            $table->text('consent_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_sepa_mandates');
    }
};
