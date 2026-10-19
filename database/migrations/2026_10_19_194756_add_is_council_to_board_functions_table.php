<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die Rats-Funktion (bei Schützengilden „Schützenrat“), die Schriftführer/
     * Kassier automatisch erhalten, wurde bisher über ihren Namen gefunden.
     * Ein Flag macht sie umbenennbar (z. B. „Ausschuss“, „Beirat“).
     */
    public function up(): void
    {
        Schema::table('tb_board_functions', function (Blueprint $table) {
            $table->boolean('is_council')->default(false)->after('implies_schuetzenrat');
        });

        DB::table('tb_board_functions')
            ->where('name', 'Schützenrat')
            ->update(['is_council' => true]);
    }

    public function down(): void
    {
        Schema::table('tb_board_functions', function (Blueprint $table) {
            $table->dropColumn('is_council');
        });
    }
};
