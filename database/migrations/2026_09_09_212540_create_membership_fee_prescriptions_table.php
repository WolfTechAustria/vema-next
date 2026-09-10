<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_membership_fee_prescriptions', function (Blueprint $table) {
            $table->bigIncrements('prescriptionID');

            $table->unsignedBigInteger('entryID');
            $table->unsignedBigInteger('memberID');
            $table->unsignedBigInteger('yearID');

            $table->string('file_path');
            $table->string('file_name');

            $table->string('sent_to')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index('entryID');
            $table->index('memberID');
            $table->index('yearID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_membership_fee_prescriptions');
    }
};
