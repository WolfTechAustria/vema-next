<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_external_contacts', function (Blueprint $table) {
            $table->bigIncrements('externalContactID');

            $table->string('name', 150);
            $table->string('surname', 150);

            $table->string('organization', 200)->nullable();

            $table->string('email', 255);
            $table->string('phone', 100)->nullable();

            $table->text('note')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('email');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_external_contacts');
    }
};
