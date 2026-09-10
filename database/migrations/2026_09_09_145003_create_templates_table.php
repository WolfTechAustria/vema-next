<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_templates', function (Blueprint $table) {
            $table->increments('templateID');

            $table->string('key', 100)->unique();
            $table->string('name', 150);

            $table->string('subject', 255)->nullable();

            $table->longText('body_html');

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_templates');
    }
};
