<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumente zu einem Mitglied (Beitrittserklärung, Vereinbarungen …),
     * auf Wunsch auch im Mitgliederbereich sichtbar.
     */
    public function up(): void
    {
        Schema::create('tb_member_files', function (Blueprint $table) {
            $table->bigIncrements('fileID');

            $table->integer('memberID');

            // Anzeigename (änderbar) und ursprünglicher Dateiname des Uploads.
            $table->string('name', 255);
            $table->string('original_name', 255);

            $table->string('file_path', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->string('category', 100)->nullable();
            $table->text('note')->nullable();

            $table->boolean('visible_to_member')->default(false);

            $table->integer('uploaded_by')->nullable();

            $table->timestamps();

            $table->index('memberID');
            $table->index('category');

            $table->foreign('memberID')
                ->references('memberID')
                ->on('tb_members')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_files');
    }
};
