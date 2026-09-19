<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_dutyplan_roles', function (Blueprint $table) {
            $table->bigIncrements('roleID');

            $table->unsignedInteger('planID');
            $table->foreign('planID')
                ->references('planID')
                ->on('tb_dutyplans')
                ->cascadeOnDelete();

            $table->string('name', 150);

            $table->unsignedTinyInteger('weekday_mask')->default(0);
            $table->unsignedSmallInteger('required_helpers')->default(1);

            $table->unsignedBigInteger('requiredGroupID')->nullable();
            $table->foreign('requiredGroupID')
                ->references('groupID')
                ->on('tb_recipient_groups')
                ->nullOnDelete();
            $table->unsignedTinyInteger('required_group_min')->default(1);

            $table->unsignedBigInteger('requiredSkillID')->nullable();
            $table->foreign('requiredSkillID')
                ->references('skillID')
                ->on('tb_skills')
                ->nullOnDelete();

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['planID', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_dutyplan_roles');
    }
};
