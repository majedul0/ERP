<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SaveAttendance
{
    /**
     * Apply a batch of changed cells from the grid.
     *
     * Only the cells somebody actually touched arrive here, which is what lets
     * two supervisors work the same month at once: each save writes the cells
     * it names and leaves every other one exactly as it found it. A whole-month
     * submission would have the second save undo the first.
     *
     * Two statements do the work whatever the batch size — one `upsert()` for
     * the marks and one delete for the cleared cells — because a grid save is a
     * bulk operation and a row-at-a-time loop turns a bulk-fill of a department
     * into a hundred round trips.
     *
     * @param  list<array{employee_id: int, day: int, status: string|null, note?: string|null}>  $marks
     * @return int How many cells were changed.
     */
    public function handle(Team $team, CarbonInterface $month, array $marks, ?User $actor = null): int
    {
        if ($marks === []) {
            return 0;
        }

        /*
         * Every id is re-checked against this company's own employees. The form
         * request validated them too, but an action is called from places a
         * request never reaches, and the cost is one query.
         */
        $employeeIds = $team->employees()
            ->whereIn('id', array_unique(array_column($marks, 'employee_id')))
            ->pluck('id')
            ->all();

        $allowed = array_flip($employeeIds);
        $now = now();

        $upserts = [];

        /*
         * Cleared cells, grouped by employee: `[employee_id => [date, ...]]`.
         *
         * Grouped rather than two flat lists, because deleting
         * `whereIn(employee) AND whereIn(date)` is a cross product — clearing
         * Rahim's 3rd and Karim's 7th would also wipe Rahim's 7th and Karim's
         * 3rd, cells nobody touched.
         */
        $cleared = [];

        foreach ($marks as $mark) {
            if (! isset($allowed[$mark['employee_id']])) {
                continue;
            }

            $date = $month->copy()->startOfMonth()->addDays($mark['day'] - 1)->toDateString();

            if (($mark['status'] ?? null) === null) {
                // Clearing a cell removes the row: "no mark" is a state, and a
                // lingering row would hold the unique index against re-marking.
                $cleared[$mark['employee_id']][] = $date;

                continue;
            }

            $upserts[] = [
                'team_id' => $team->id,
                'employee_id' => $mark['employee_id'],
                'marked_by' => $actor?->id,
                'date' => $date,
                'status' => $mark['status'],
                'note' => $mark['note'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($upserts === [] && $cleared === []) {
            return 0;
        }

        return DB::transaction(function () use ($upserts, $cleared, $team): int {
            if ($upserts !== []) {
                AttendanceRecord::upsert(
                    $upserts,
                    uniqueBy: ['employee_id', 'date'],
                    update: ['status', 'note', 'marked_by', 'updated_at'],
                );
            }

            $clearedCount = 0;

            if ($cleared !== []) {
                /*
                 * One query, one OR group per employee, so only the exact cells
                 * named are removed. The number of groups is bounded by how
                 * many people are on screen, not by how many cells changed.
                 */
                AttendanceRecord::query()
                    ->where('team_id', $team->id)
                    ->where(function ($query) use ($cleared, &$clearedCount) {
                        foreach ($cleared as $employeeId => $dates) {
                            $dates = array_unique($dates);
                            $clearedCount += count($dates);

                            $query->orWhere(fn ($group) => $group
                                ->where('employee_id', $employeeId)
                                ->whereIn('date', $dates));
                        }
                    })
                    ->delete();
            }

            return count($upserts) + $clearedCount;
        });
    }
}
