<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legt die Tabellen aus dem Alt-VEMA an, die bisher nur in der bestehenden
     * Datenbank existierten. Spätere Migrationen verändern sie, daher wird hier
     * der Stand VOR diesen Migrationen nachgebildet.
     *
     * Auf bestehenden Installationen existieren alle Tabellen bereits — die
     * hasTable()-Guards machen die Migration dort zum No-op. Auf einer leeren
     * Datenbank (neuer Verein, Tests) entsteht so das vollständige Schema.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tb_members')) {
            Schema::create('tb_members', function (Blueprint $table) {
                $table->integer('memberID', true);
                $table->string('gender', 4);
                $table->string('name', 100);
                $table->string('surname', 100);
                $table->string('street', 100);
                $table->integer('zip');
                $table->integer('tlsbID')->nullable();
                $table->tinyInteger('competitionMember')->nullable();
                $table->tinyInteger('supportingMember')->nullable();
                $table->date('dateOfBirth')->nullable();
                $table->date('dateOfJoin')->nullable();
                $table->tinyInteger('active')->default(1);
                $table->date('deactiveSince')->nullable();
                $table->integer('board_function')->nullable();
            });
        }

        if (! Schema::hasTable('tb_user')) {
            Schema::create('tb_user', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('username', 100);
                $table->string('password', 100);
                $table->string('email', 100);
                $table->tinyInteger('enabled')->default(0);
                $table->integer('function')->default(0);
                $table->text('companyname')->nullable();
                $table->text('companyname_short');
                $table->timestamp('registerDate')->useCurrent();
                $table->integer('memberID');
            });
        }

        if (! Schema::hasTable('tb_email')) {
            Schema::create('tb_email', function (Blueprint $table) {
                $table->integer('ID', true);
                $table->integer('memberID');
                $table->string('email', 100);
            });
        }

        if (! Schema::hasTable('tb_phone')) {
            Schema::create('tb_phone', function (Blueprint $table) {
                $table->integer('ID', true);
                $table->integer('phoneCategory');
                $table->string('phoneNumber', 100);
                $table->integer('memberID');
            });
        }

        if (! Schema::hasTable('tb_city')) {
            Schema::create('tb_city', function (Blueprint $table) {
                $table->integer('ID', true);
                $table->integer('zip');
                $table->string('city', 100);
            });
        }

        if (! Schema::hasTable('tb_dutyplan_events')) {
            Schema::create('tb_dutyplan_events', function (Blueprint $table) {
                $table->integer('eventID', true);
                $table->string('duty_type', 50);
                $table->date('duty_date');
                $table->string('duty_name', 100)->default('Dienst');
                $table->unsignedTinyInteger('required_helpers');
                $table->string('note')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['duty_date', 'duty_type'], 'uq_dutyplan_date_type');
                $table->index('duty_date', 'idx_dutyplan_date');
            });
        }

        if (! Schema::hasTable('tb_dutyplan_assignments')) {
            Schema::create('tb_dutyplan_assignments', function (Blueprint $table) {
                $table->integer('assignmentID', true);
                $table->integer('eventID');
                $table->unsignedTinyInteger('slot_no');
                $table->integer('memberID');
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['eventID', 'slot_no'], 'uq_dutyplan_event_slot');
                $table->unique(['eventID', 'memberID'], 'uq_dutyplan_event_member');
                $table->index('memberID', 'idx_dutyplan_assignment_member');

                $table->foreign('eventID', 'fk_dutyplan_assignment_event')
                    ->references('eventID')
                    ->on('tb_dutyplan_events')
                    ->cascadeOnDelete();

                $table->foreign('memberID', 'fk_dutyplan_assignment_member')
                    ->references('memberID')
                    ->on('tb_members');
            });
        }

        if (! Schema::hasTable('tb_dutyplan_absences')) {
            Schema::create('tb_dutyplan_absences', function (Blueprint $table) {
                $table->integer('absenceID', true);
                $table->integer('memberID');
                $table->date('date_from');
                $table->date('date_to');
                $table->string('note')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('memberID', 'idx_dutyplan_absence_member');
                $table->index(['date_from', 'date_to'], 'idx_dutyplan_absence_dates');

                $table->foreign('memberID', 'fk_dutyplan_absence_member')
                    ->references('memberID')
                    ->on('tb_members')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Bewusst leer: Die Tabellen enthalten auf bestehenden Installationen
     * Vereinsdaten und dürfen durch ein Rollback nie gelöscht werden.
     */
    public function down(): void
    {
        //
    }
};
