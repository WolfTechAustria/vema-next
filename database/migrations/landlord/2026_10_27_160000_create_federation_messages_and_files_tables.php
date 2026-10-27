<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mitteilungen eines Verbands an seine Mitgliedsvereine.
        Schema::create('federation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('subject', 200);
            $table->text('body');
            $table->string('sent_by', 100);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });

        // Dokumente des Verbands; mit federation_message_id als Anhang einer Mitteilung.
        // Die Dateien liegen im Speicher des Verbands.
        Schema::create('federation_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('federation_message_id')->nullable()->constrained('federation_messages')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });

        // Wann ein Verein eine Mitteilung geöffnet hat (für „neu“).
        Schema::create('federation_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_message_id')->constrained('federation_messages')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('tenants')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['federation_message_id', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_message_reads');
        Schema::dropIfExists('federation_files');
        Schema::dropIfExists('federation_messages');
    }
};
