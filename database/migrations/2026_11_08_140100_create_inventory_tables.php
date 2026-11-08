<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kategorien entstehen beim ersten Eintippen und stehen danach als Vorschlag bereit.
        Schema::create('tb_inventory_categories', function (Blueprint $table) {
            $table->bigIncrements('categoryID');
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('tb_inventory_items', function (Blueprint $table) {
            $table->bigIncrements('itemID');
            $table->unsignedBigInteger('categoryID')->nullable();
            $table->string('name', 150);
            $table->string('inventory_number', 50)->nullable()->unique();
            $table->string('location', 150)->nullable();
            $table->string('condition', 20)->default('good');
            $table->date('purchased_on')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('inspection_interval_months')->nullable();
            $table->date('next_inspection_on')->nullable()->index();
            $table->timestamps();

            $table->foreign('categoryID')->references('categoryID')->on('tb_inventory_categories')->nullOnDelete();
        });

        Schema::create('tb_inventory_loans', function (Blueprint $table) {
            $table->bigIncrements('loanID');
            $table->unsignedBigInteger('itemID');
            // memberID ist in tb_members ein signed int — daher ohne Fremdschlüssel.
            $table->integer('memberID')->index();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->foreign('itemID')->references('itemID')->on('tb_inventory_items')->cascadeOnDelete();
        });

        Schema::create('tb_inventory_inspections', function (Blueprint $table) {
            $table->bigIncrements('inspectionID');
            $table->unsignedBigInteger('itemID');
            $table->date('inspected_on');
            $table->string('result', 20);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('itemID')->references('itemID')->on('tb_inventory_items')->cascadeOnDelete();
        });

        // Bereits verschickte Erinnerungen (Prüfung bzw. Rückgabe je Fälligkeit).
        Schema::create('tb_inventory_reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->unsignedBigInteger('reference_id');
            $table->date('due_on');
            $table->timestamps();

            $table->unique(['kind', 'reference_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_inventory_reminders_sent');
        Schema::dropIfExists('tb_inventory_inspections');
        Schema::dropIfExists('tb_inventory_loans');
        Schema::dropIfExists('tb_inventory_items');
        Schema::dropIfExists('tb_inventory_categories');
    }
};
