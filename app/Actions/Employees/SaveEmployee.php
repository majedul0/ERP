<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaveEmployee
{
    /**
     * Add somebody to the payroll, or change their details.
     *
     * One method for both, so the photo handling — which is the only part with
     * a file on disk to get wrong — is written once.
     *
     * Nothing here reaches backwards. A leaving date stops payroll counting
     * this person from that month on and leaves every payslip already issued
     * exactly as it was printed, and a change of department does not rewrite
     * which department paid them last March.
     *
     * `balance` is deliberately absent: it is derived from the approved payroll
     * lines and the salary payments by ReplayEmployeeBalance, and a figure
     * typed here would be wiped by the first replay.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(
        Team $team,
        array $data,
        ?Employee $employee = null,
        ?UploadedFile $photo = null,
        ?User $actor = null,
    ): Employee {
        $photoPath = null;

        try {
            return DB::transaction(function () use ($team, $data, $employee, $photo, $actor, &$photoPath): Employee {
                $attributes = [
                    'department_id' => $data['department_id'] ?? null,
                    'employee_code' => $data['employee_code'],
                    'name' => $data['name'],
                    'father_name' => $data['father_name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'nid' => $data['nid'] ?? null,
                    'designation' => $data['designation'] ?? null,
                    'address' => $data['address'] ?? null,
                    'thana' => $data['thana'] ?? null,
                    'district' => $data['district'] ?? null,
                    'division' => $data['division'] ?? null,
                    'salary_type' => $data['salary_type'],
                    'joined_on' => $data['joined_on'],
                    'left_on' => $data['left_on'] ?? null,
                ];

                if ($employee === null) {
                    $employee = Employee::create([
                        ...$attributes,
                        'team_id' => $team->id,
                        'created_by' => $actor?->id,
                    ]);

                    $previousPhoto = null;
                } else {
                    /*
                     * The previous path is read from the locked row, not from
                     * the model handed in — two people saving at once would
                     * otherwise each delete the other's file.
                     */
                    $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                    $previousPhoto = $employee->photo_path;
                }

                if ($photo) {
                    // Named from the previous path, so a replacement lands at
                    // employee-emp-001-2.jpg and no cached copy of -1 can mask it.
                    $photoPath = TenantFileStore::put(
                        'employee_photos',
                        $team->id,
                        $photo,
                        previousPath: $previousPhoto,
                        nameSuffix: $attributes['employee_code'],
                    );

                    $attributes['photo_path'] = $photoPath;
                }

                $employee->update($attributes);

                // Only once the new path is committed.
                if ($photoPath !== null && $previousPhoto !== $photoPath) {
                    TenantFileStore::delete('employee_photos', $previousPhoto);
                }

                return $employee;
            });
        } catch (Throwable $e) {
            TenantFileStore::delete('employee_photos', $photoPath);

            throw $e;
        }
    }
}
