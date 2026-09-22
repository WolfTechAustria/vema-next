<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_board_functions', function (Blueprint $table) {
            $table->id('boardFunctionID');
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_top_vorstand')->default(false);
            $table->boolean('implies_schuetzenrat')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tb_member_board_function_assignments', function (Blueprint $table) {
            $table->id('assignmentID');

            /*
             * Kein FK auf tb_members: memberID ist dort ein signed int,
             * nicht bigint (siehe tb_skill_members) — MySQL verlangt
             * exakte Typgleichheit für Fremdschlüssel.
             */
            $table->unsignedBigInteger('memberID');

            $table->unsignedBigInteger('boardFunctionID');
            $table->foreign('boardFunctionID')
                ->references('boardFunctionID')
                ->on('tb_board_functions')
                ->cascadeOnDelete();

            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['memberID', 'date_to']);
        });

        $now = now();

        DB::table('tb_board_functions')->insert([
            ['name' => 'Oberschützenmeister', 'sort_order' => 1, 'is_top_vorstand' => true, 'implies_schuetzenrat' => false, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '1. Schützenmeister', 'sort_order' => 2, 'is_top_vorstand' => true, 'implies_schuetzenrat' => false, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '2. Schützenmeister', 'sort_order' => 3, 'is_top_vorstand' => true, 'implies_schuetzenrat' => false, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Schriftführer', 'sort_order' => 4, 'is_top_vorstand' => false, 'implies_schuetzenrat' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Schriftführer Stv.', 'sort_order' => 5, 'is_top_vorstand' => false, 'implies_schuetzenrat' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kassier', 'sort_order' => 6, 'is_top_vorstand' => false, 'implies_schuetzenrat' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kassier Stv.', 'sort_order' => 7, 'is_top_vorstand' => false, 'implies_schuetzenrat' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Schützenrat', 'sort_order' => 8, 'is_top_vorstand' => false, 'implies_schuetzenrat' => false, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_board_function_assignments');
        Schema::dropIfExists('tb_board_functions');
    }
};
