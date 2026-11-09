<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Online eingereichte Beitrittserklärungen; werden erst nach Freigabe zu Mitgliedern.
        Schema::create('tb_member_applications', function (Blueprint $table) {
            $table->bigIncrements('applicationID');
            $table->string('gender', 4)->nullable();
            $table->string('name', 100);
            $table->string('surname', 100);
            $table->date('dateOfBirth')->nullable();
            $table->string('street')->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('email');
            $table->string('phone', 100)->nullable();
            $table->string('membership_type', 150)->nullable();
            // ordinary | supporting | none (MembershipKind)
            $table->string('membership_kind', 20)->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->date('applied_on');
            $table->string('place', 100)->nullable();
            $table->string('signature_path')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('consents')->nullable();
            $table->string('verification_token_hash', 64)->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            // unverified | pending | approved | rejected
            $table->string('status', 20)->index();
            // memberID ist in tb_members ein signed int — daher ohne Fremdschlüssel.
            $table->integer('memberID')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_applications');
    }
};
