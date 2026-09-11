<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_recipient_groups', function (Blueprint $table) {
            $table->bigIncrements('groupID');

            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->timestamps();
        });

        Schema::create('tb_recipient_group_members', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('groupID');
            $table->unsignedBigInteger('memberID');

            $table->timestamps();

            $table->unique([
                'groupID',
                'memberID',
            ]);

            $table->index('memberID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_recipient_group_members');
        Schema::dropIfExists('tb_recipient_groups');
    }
};
