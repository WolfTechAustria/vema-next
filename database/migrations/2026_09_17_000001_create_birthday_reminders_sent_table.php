<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_birthday_reminders_sent', function (Blueprint $table) {
            $table->id('reminderID');
            $table->integer('memberID');
            $table->unsignedSmallInteger('age');
            $table->date('birthday_date');
            $table->timestamp('sent_at');

            $table->foreign('memberID')
                ->references('memberID')
                ->on('tb_members')
                ->cascadeOnDelete();

            $table->unique(
                ['memberID', 'age'],
                'birthday_reminders_member_age_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_birthday_reminders_sent');
    }
};
