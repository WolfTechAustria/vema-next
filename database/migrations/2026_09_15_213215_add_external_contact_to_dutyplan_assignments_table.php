<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_dutyplan_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('externalContactID')
                ->nullable()
                ->after('memberID');

            $table->index('externalContactID');

            /*
             * Typ bleibt signed int wie tb_members.memberID — sonst scheitert
             * MySQL am bestehenden Fremdschlüssel fk_dutyplan_assignment_member.
             */
            $table->integer('memberID')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tb_dutyplan_assignments', function (Blueprint $table) {
            $table->dropIndex(['externalContactID']);
            $table->dropColumn('externalContactID');

            $table->integer('memberID')
                ->nullable(false)
                ->change();
        });
    }
};
