<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tausch- und Abgabeanfragen von Diensten zwischen Mitgliedern.
     * "targetMemberID" leer = Abgabe an alle im Dienstplan.
     */
    public function up(): void
    {
        Schema::create('tb_duty_swap_requests', function (Blueprint $table) {
            $table->id('swapID');
            $table->string('type', 20);
            $table->integer('requesterID')->index();
            $table->integer('assignmentID');
            $table->integer('targetMemberID')->nullable()->index();
            $table->integer('targetAssignmentID')->nullable()->index();
            $table->integer('acceptedByID')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('message', 500)->nullable();
            $table->integer('decidedByUserID')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['assignmentID', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_duty_swap_requests');
    }
};
