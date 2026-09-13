<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_circular_recipients', function (Blueprint $table) {
            $table->unsignedBigInteger('externalContactID')
                ->nullable()
                ->after('memberID');

            $table->index('externalContactID');

            $table->unsignedBigInteger('memberID')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tb_circular_recipients', function (Blueprint $table) {
            $table->dropIndex(['externalContactID']);
            $table->dropColumn('externalContactID');

            $table->unsignedBigInteger('memberID')
                ->nullable(false)
                ->change();
        });
    }
};
