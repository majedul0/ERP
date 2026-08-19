<?php

namespace App\Http\Controllers\Employees;

use App\Actions\Employees\SaveEmployee;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\SalaryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\SaveEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * The people who work here.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $employees = $team->employees()
            ->with('department')
            ->orderBy('name')
            ->get();

        return Inertia::render('company/hr/employees/index', [
            'employees' => $employees
                ->map(fn (Employee $employee) => $this->summary($employee))
                ->all(),
            'activeCount' => $employees->filter(fn (Employee $employee) => $employee->isActive())->count(),
        ]);
    }

    /**
     * Show the registration form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('company/hr/employees/create', $this->formOptions($request));
    }

    /**
     * Add somebody.
     */
    public function store(SaveEmployeeRequest $request, SaveEmployee $saveEmployee): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $employee = $saveEmployee->handle(
            team: $team,
            data: $request->safe()->except('photo'),
            photo: $request->file('photo'),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name added.', ['name' => $employee->name]),
        ]);

        return to_route('employees.index', ['current_team' => $team->slug]);
    }

    /**
     * One person's record.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function show(Request $request, string $current_team, Employee $employee): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($employee->team_id === $team->id, 404);

        $employee->load('department');

        return Inertia::render('company/hr/employees/show', [
            'employee' => $this->detail($employee),
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(Request $request, string $current_team, Employee $employee): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($employee->team_id === $team->id, 404);

        return Inertia::render('company/hr/employees/edit', [
            ...$this->formOptions($request),
            'employee' => $this->detail($employee),
        ]);
    }

    /**
     * Save the changes.
     */
    public function update(
        SaveEmployeeRequest $request,
        string $current_team,
        Employee $employee,
        SaveEmployee $saveEmployee,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($employee->team_id === $team->id, 404);

        $saveEmployee->handle(
            team: $team,
            data: $request->safe()->except('photo'),
            employee: $employee,
            photo: $request->file('photo'),
            actor: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.show', [
            'current_team' => $team->slug,
            'employee' => $employee->id,
        ]);
    }

    /**
     * Remove somebody from the registry.
     *
     * Soft, always. A person who has been paid appears on payslips, on the
     * financial report and in their own ledger, and none of those may develop a
     * hole because somebody tidied up the list. Recording a leaving date is
     * what "they no longer work here" actually means — this is for a record
     * created by mistake.
     */
    public function destroy(Request $request, string $current_team, Employee $employee): RedirectResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($employee->team_id === $team->id, 404);

        $employee->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee removed.')]);

        return to_route('employees.index', ['current_team' => $team->slug]);
    }

    /**
     * The lists both forms pick from.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $team = $this->currentTeam($request);

        return [
            'departments' => $team->departments()
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->all(),
            'salaryTypes' => SalaryType::options(),
        ];
    }

    /**
     * A row on the list.
     *
     * Carries no money at all — the registry is readable with `employee:view`,
     * and what somebody is paid sits behind `payroll:view`. Omitted rather than
     * sent and hidden, because a prop the browser receives has been disclosed
     * whatever the page does with it.
     *
     * @return array<string, mixed>
     */
    private function summary(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employeeCode' => $employee->employee_code,
            'name' => $employee->name,
            'designation' => $employee->designation,
            'departmentName' => $employee->department?->name,
            'phone' => $employee->phone,
            'salaryType' => $employee->salary_type->value,
            'salaryTypeLabel' => $employee->salary_type->label(),
            'joinedOn' => $employee->joined_on->toDateString(),
            'leftOn' => $employee->left_on?->toDateString(),
            'isActive' => $employee->isActive(),
            'photoUrl' => $employee->photoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Employee $employee): array
    {
        return [
            ...$this->summary($employee),
            'departmentId' => $employee->department_id,
            'fatherName' => $employee->father_name,
            'nid' => $employee->nid,
            'address' => $employee->address,
            'thana' => $employee->thana,
            'district' => $employee->district,
            'division' => $employee->division,
            'fullAddress' => $employee->fullAddress(),
        ];
    }
}
