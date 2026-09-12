<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_member_login_tokens', function (Blueprint $table) {
            $table->bigIncrements('tokenID');

            $table->unsignedBigInteger('memberID');

            $table->string('email');

            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');

            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index('memberID');
            $table->index('email');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_login_tokens');
    }
};
