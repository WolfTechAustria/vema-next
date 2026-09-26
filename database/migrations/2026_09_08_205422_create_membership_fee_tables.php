<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_membership_fee_years', function (Blueprint $table) {
            $table->increments('yearID');

            $table->unsignedSmallInteger('year')->unique();

            $table->string('name', 150);

            $table->decimal('default_amount', 10, 2)
                ->nullable();

            $table->date('due_date')
                ->nullable();

            $table->boolean('active')
                ->default(true);

            $table->timestamps();
        });

        Schema::create('tb_membership_fee_entries', function (Blueprint $table) {
            $table->increments('entryID');

            $table->unsignedInteger('yearID');
            $table->integer('memberID');

            $table->decimal('amount', 10, 2)
                ->nullable();

            $table->string('status', 20)
                ->default('open');

            $table->dateTime('paid_at')
                ->nullable();

            $table->string('note', 255)
                ->nullable();

            $table->timestamps();

            $table->unique([
                'yearID',
                'memberID',
            ]);

            $table->foreign('yearID')
                ->references('yearID')
                ->on('tb_membership_fee_years')
                ->cascadeOnDelete();
        });

        /*
         * Übernahme aus dem Alt-VEMA — nur auf der bestehenden Installation.
         * Eine neue, leere Datenbank startet ohne Beitragsjahre.
         */
        if (! Schema::hasTable('tb_membershipfee')) {
            return;
        }

        $years = range(2013, 2026);

        foreach ($years as $year) {
            $yearId = DB::table('tb_membership_fee_years')
                ->insertGetId([
                    'year' => $year,
                    'name' => 'Mitgliedsbeitrag '.$year,
                    'default_amount' => null,
                    'due_date' => null,
                    'active' => $year === 2026,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $column = 'fee'.$year;

            $legacyRows = DB::table('tb_membershipfee')
                ->select(
                    'memberID',
                    DB::raw("MAX(`{$column}`) AS fee_status")
                )
                ->whereNotNull($column)
                ->groupBy('memberID')
                ->get();

            foreach ($legacyRows as $legacyRow) {
                DB::table('tb_membership_fee_entries')
                    ->insert([
                        'yearID' => $yearId,
                        'memberID' => $legacyRow->memberID,

                        // Historisch unbekannt.
                        'amount' => null,

                        'status' => ((int) $legacyRow->fee_status) === 1
                            ? 'paid'
                            : 'open',

                        // Das exakte historische Zahlungsdatum
                        // wurde nicht gespeichert.
                        'paid_at' => null,

                        'note' => 'Aus Legacy-VEMA übernommen',

                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_membership_fee_entries');
        Schema::dropIfExists('tb_membership_fee_years');
    }
};
