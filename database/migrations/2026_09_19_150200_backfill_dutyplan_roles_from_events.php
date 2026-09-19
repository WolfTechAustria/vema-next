<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('tb_dutyplans')->orderBy('planID')->get(['planID']);

        foreach ($plans as $plan) {
            $events = DB::table('tb_dutyplan_events')
                ->where('planID', $plan->planID)
                ->whereNull('roleID')
                ->orderBy('duty_date')
                ->get(['eventID', 'duty_date', 'duty_name', 'required_helpers']);

            $groups = [];

            foreach ($events as $event) {
                $name = $event->duty_name ?: 'Dienst';
                $requiredHelpers = max(1, (int) $event->required_helpers);
                $key = $name . '|' . $requiredHelpers;

                $weekday = Carbon::parse($event->duty_date)->isoWeekday();

                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'name' => $name,
                        'required_helpers' => $requiredHelpers,
                        'mask' => 0,
                        'eventIDs' => [],
                    ];
                }

                $groups[$key]['mask'] |= (1 << ($weekday - 1));
                $groups[$key]['eventIDs'][] = $event->eventID;
            }

            $sortOrder = 0;

            foreach ($groups as $group) {
                $now = now();

                $roleID = DB::table('tb_dutyplan_roles')->insertGetId([
                    'planID' => $plan->planID,
                    'name' => $group['name'],
                    'weekday_mask' => $group['mask'],
                    'required_helpers' => $group['required_helpers'],
                    'requiredGroupID' => null,
                    'required_group_min' => 1,
                    'requiredSkillID' => null,
                    'start_time' => null,
                    'end_time' => null,
                    'sort_order' => $sortOrder++,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (array_chunk($group['eventIDs'], 500) as $chunk) {
                    DB::table('tb_dutyplan_events')
                        ->whereIn('eventID', $chunk)
                        ->update([
                            'roleID' => $roleID,
                            'duty_type' => 'role_' . $roleID,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('tb_dutyplan_events')
            ->whereNotNull('roleID')
            ->orderBy('eventID')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('tb_dutyplan_events')
                        ->where('eventID', $row->eventID)
                        ->update([
                            'duty_type' => 'weekday_' . Carbon::parse($row->duty_date)->isoWeekday(),
                            'roleID' => null,
                        ]);
                }
            }, 'eventID');

        DB::table('tb_dutyplan_roles')->delete();
    }
};
