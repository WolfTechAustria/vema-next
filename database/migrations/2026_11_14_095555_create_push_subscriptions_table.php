<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Web-Push-Abos je Gerät (Funktionär oder Mitglieder-Account) und die
     * gewählten Kategorien je Person (leer = alle).
     */
    public function up(): void
    {
        Schema::create('tb_push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', 1024)->charset('ascii')->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });

        Schema::table('tb_user', function (Blueprint $table) {
            $table->json('push_categories')->nullable();
        });

        Schema::table('tb_member_accounts', function (Blueprint $table) {
            $table->json('push_categories')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_push_subscriptions');

        Schema::table('tb_user', function (Blueprint $table) {
            $table->dropColumn('push_categories');
        });

        Schema::table('tb_member_accounts', function (Blueprint $table) {
            $table->dropColumn('push_categories');
        });
    }
};
