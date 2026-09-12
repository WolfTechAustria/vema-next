<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_member_account_members', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('accountID');
            $table->unsignedBigInteger('memberID');

            $table->string('relation', 30)
                ->default('self');

            $table->timestamps();

            $table->unique([
                'accountID',
                'memberID',
            ]);

            $table->index('memberID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_account_members');
    }
};
