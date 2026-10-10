<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_events', function (Blueprint $table) {
            $table->bigIncrements('eventID');

            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);

            $table->boolean('rsvp_enabled')->default(true);

            $table->timestamps();

            $table->index('starts_at');
        });

        Schema::create('tb_event_responses', function (Blueprint $table) {
            $table->bigIncrements('responseID');

            $table->unsignedBigInteger('eventID');
            $table->integer('memberID');

            $table->string('status', 20);

            $table->timestamps();

            $table->unique([
                'eventID',
                'memberID',
            ]);

            $table->index('memberID');

            $table->foreign('eventID')
                ->references('eventID')
                ->on('tb_events')
                ->cascadeOnDelete();
        });

        /*
         * 1:1 zu tb_members, analog zu tb_member_duty_settings — eigener
         * Abo-Link für den Vereinskalender, unabhängig vom Dienstplan.
         */
        Schema::create('tb_member_event_settings', function (Blueprint $table) {
            $table->integer('memberID')->primary();

            $table->string('ical_token', 64)->nullable()->unique();
            $table->timestamp('ical_token_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_event_settings');
        Schema::dropIfExists('tb_event_responses');
        Schema::dropIfExists('tb_events');
    }
};
