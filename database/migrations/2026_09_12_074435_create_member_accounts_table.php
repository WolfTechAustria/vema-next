<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_member_accounts', function (Blueprint $table) {
            $table->bigIncrements('accountID');

            $table->unsignedBigInteger('memberID')->unique();

            $table->string('email')->unique();

            $table->string('password')->nullable();

            $table->timestamp('email_verified_at')->nullable();

            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();

            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_accounts');
    }
};
