<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_activity_log', function (Blueprint $table) {
            $table->bigIncrements('activityLogID');

            $table->integer('userID')->nullable();
            $table->foreign('userID')
                ->references('id')
                ->on('tb_user')
                ->nullOnDelete();

            $table->unsignedBigInteger('memberAccountID')->nullable();
            $table->foreign('memberAccountID')
                ->references('accountID')
                ->on('tb_member_accounts')
                ->nullOnDelete();

            $table->string('action', 100);
            $table->string('description', 255);

            // Kein FK: die Ziel-Tabelle variiert je subject_type.
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['action']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_activity_log');
    }
};
