<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
public function up(): void
{
DB::statement('
ALTER TABLE tb_dutyplan_events
DROP INDEX uq_dutyplan_date_type
');

DB::statement('
ALTER TABLE tb_dutyplan_events
ADD UNIQUE KEY uq_dutyplan_plan_date_type
(planID, duty_date, duty_type)
');
}

public function down(): void
{
DB::statement('
ALTER TABLE tb_dutyplan_events
DROP INDEX uq_dutyplan_plan_date_type
');

DB::statement('
ALTER TABLE tb_dutyplan_events
ADD UNIQUE KEY uq_dutyplan_date_type
(duty_date, duty_type)
');
}
};
