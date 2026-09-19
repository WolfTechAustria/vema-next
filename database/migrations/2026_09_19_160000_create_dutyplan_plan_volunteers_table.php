<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_dutyplan_plan_volunteers', function (Blueprint $table) {
            $table->bigIncrements('planVolunteerID');

            $table->unsignedInteger('planID');
            $table->foreign('planID')
                ->references('planID')
                ->on('tb_dutyplans')
                ->cascadeOnDelete();

            /*
             * Kein FK auf tb_members: memberID ist dort ein signed int,
             * nicht bigint — MySQL verlangt exakte Typgleichheit für
             * Fremdschlüssel (siehe tb_skill_members für das gleiche Muster).
             */
            $table->unsignedBigInteger('memberID')->nullable();
            $table->index('memberID');

            $table->unsignedBigInteger('externalContactID')->nullable();
            $table->foreign('externalContactID')
                ->references('externalContactID')
                ->on('tb_external_contacts')
                ->cascadeOnDelete();

            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('weekday_mask')->default(127);

            $table->timestamps();

            $table->unique(['planID', 'memberID'], 'uq_planvol_plan_member');
            $table->unique(['planID', 'externalContactID'], 'uq_planvol_plan_external');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_dutyplan_plan_volunteers');
    }
};
