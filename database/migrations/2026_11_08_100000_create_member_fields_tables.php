<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eigene Mitgliederfelder des Vereins (Einstellungen → Mitgliederfelder).
        Schema::create('tb_member_fields', function (Blueprint $table) {
            $table->bigIncrements('fieldID');
            $table->string('name', 100)->unique();
            // Schlüssel für den Platzhalter {{feld_<key>}}
            $table->string('key', 60)->unique();
            $table->string('type', 20);
            // Auswahlmöglichkeiten (nur Typ "select")
            $table->json('options')->nullable();
            // Mitgliederportal: hidden | visible | editable
            $table->string('portal', 20)->default('hidden');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tb_member_field_values', function (Blueprint $table) {
            $table->bigIncrements('valueID');
            $table->unsignedBigInteger('fieldID');
            // memberID ist in tb_members ein signed int — daher ohne Fremdschlüssel.
            $table->integer('memberID')->index();
            $table->text('value');
            $table->timestamps();

            $table->unique(['fieldID', 'memberID']);
            $table->foreign('fieldID')->references('fieldID')->on('tb_member_fields')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_field_values');
        Schema::dropIfExists('tb_member_fields');
    }
};
