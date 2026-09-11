<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_circular_attachments', function (Blueprint $table) {
            $table->bigIncrements('attachmentID');

            $table->unsignedBigInteger('circularID');

            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamps();

            $table->index('circularID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_circular_attachments');
    }
};
