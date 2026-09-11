<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_circulars', function (Blueprint $table) {
            $table->bigIncrements('circularID');

            $table->string('title', 200);

            $table->unsignedBigInteger('templateID')
                ->nullable();

            $table->string('subject', 255)
                ->nullable();

            $table->longText('body_html');

            $table->string('status', 30)
                ->default('draft');

            $table->timestamp('sent_at')
                ->nullable();

            $table->timestamps();

            $table->index('templateID');
            $table->index('status');
        });

        Schema::create('tb_circular_recipients', function (Blueprint $table) {
            $table->bigIncrements('recipientID');

            $table->unsignedBigInteger('circularID');
            $table->unsignedBigInteger('memberID');

            $table->string('delivery_method', 20)
                ->nullable();

            $table->string('email')
                ->nullable();

            $table->string('file_path')
                ->nullable();

            $table->string('file_name')
                ->nullable();

            $table->timestamp('sent_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'circularID',
                'memberID',
            ]);

            $table->index('memberID');
            $table->index('delivery_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'tb_circular_recipients'
        );

        Schema::dropIfExists(
            'tb_circulars'
        );
    }
};
