<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_skills', function (Blueprint $table) {
            $table->bigIncrements('skillID');
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tb_skill_members', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('skillID');
            $table->foreign('skillID')
                ->references('skillID')
                ->on('tb_skills')
                ->cascadeOnDelete();

            /*
             * Kein FK auf tb_members: memberID ist dort ein signed int,
             * nicht bigint (siehe tb_member_duty_settings) — MySQL verlangt
             * exakte Typgleichheit für Fremdschlüssel.
             */
            $table->unsignedBigInteger('memberID');

            $table->timestamps();

            $table->unique(['skillID', 'memberID']);
            $table->index('memberID');
        });

        Schema::create('tb_skill_external_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('skillID');
            $table->foreign('skillID')
                ->references('skillID')
                ->on('tb_skills')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('externalContactID');
            $table->foreign('externalContactID')
                ->references('externalContactID')
                ->on('tb_external_contacts')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['skillID', 'externalContactID']);
            $table->index('externalContactID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_skill_external_contacts');
        Schema::dropIfExists('tb_skill_members');
        Schema::dropIfExists('tb_skills');
    }
};
