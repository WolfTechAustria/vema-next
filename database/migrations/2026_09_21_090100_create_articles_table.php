<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_articles', function (Blueprint $table) {
            $table->bigIncrements('articleID');

            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('Stk');

            $table->decimal('price_net', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(20.00);

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_articles');
    }
};
