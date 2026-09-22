<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_member_membership_periods', function (Blueprint $table) {
            $table->id('membershipPeriodID');

            /*
             * Kein FK auf tb_members: memberID ist dort ein signed int,
             * nicht bigint (siehe tb_skill_members) — MySQL verlangt
             * exakte Typgleichheit für Fremdschlüssel.
             */
            $table->unsignedBigInteger('memberID');

            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('note', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['memberID', 'date_to']);
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_membership_periods');
    }
};
