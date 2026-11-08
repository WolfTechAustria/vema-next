<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bezeichnungen der Ehrungen — entstehen beim ersten Eintragen und
        // stehen danach bei allen Mitgliedern als Vorschlag bereit.
        Schema::create('tb_honors', function (Blueprint $table) {
            $table->bigIncrements('honorID');
            $table->string('name', 150)->unique();
            $table->timestamps();
        });

        Schema::create('tb_member_honors', function (Blueprint $table) {
            $table->bigIncrements('memberHonorID');
            // memberID ist in tb_members ein signed int — daher ohne Fremdschlüssel.
            $table->integer('memberID')->index();
            $table->unsignedBigInteger('honorID');
            $table->date('awarded_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->foreign('honorID')->references('honorID')->on('tb_honors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_honors');
        Schema::dropIfExists('tb_honors');
    }
};
