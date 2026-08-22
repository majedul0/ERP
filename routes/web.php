<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Distributors\DistributorController;
use App\Http\Controllers\Distributors\StatementController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Employees\DepartmentController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\ReportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\BonusController;
use App\Http\Controllers\Hr\HolidayController;
use App\Http\Controllers\Hr\PayrollRunController;
use App\Http\Controllers\Hr\PayslipController;
use App\Http\Controllers\Hr\SalaryPaymentController;
use App\Http\Controllers\Hr\SalaryRateController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Payments\BankController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\StockMovementController;
use App\Http\Controllers\Products\StockReportController;
use App\Http\Controllers\RawMaterials\MaterialPurchaseController;
use App\Http\Controllers\RawMaterials\RawMaterialController;
use App\Http\Controllers\RawMaterials\StockLevelController;
use App\Http\Controllers\SalesReturns\SalesReturnController;
use App\Http\Controllers\SuspendedController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Vendors\VendorBillController;
use App\Http\Controllers\Vendors\VendorController;
use App\Http\Controllers\Vendors\VendorPaymentController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureTeamPermission;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
 * The platform panel, for whoever runs this system rather than the companies
 * using it. Deliberately at a plain, unadvertised path — nothing links to it.
 */
Route::prefix('majedul')->group(function () {
    Route::get('/', [PlatformController::class, 'showLogin'])->name('platform.login');
    Route::post('/', [PlatformController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('platform.login.store');

    /*
     * No `auth` middleware: it would bounce a visitor to the company login.
     * EnsureSuperAdmin sends them to the platform login instead, and answers
     * 404 to a signed-in company user so the panel does not announce itself.
     */
    Route::middleware(EnsureSuperAdmin::class)->group(function () {
        Route::get('companies', [PlatformController::class, 'index'])->name('platform.index');
        Route::post('companies', [PlatformController::class, 'store'])->name('platform.companies.store');
        Route::patch('companies/{team}/suspension', [PlatformController::class, 'suspend'])->name('platform.companies.suspend');
        Route::patch('companies/{team}/plan', [SubscriptionController::class, 'assignPlan'])->name('platform.companies.plan');
        Route::post('companies/{team}/payments', [SubscriptionController::class, 'store'])->name('platform.payments.store');
        Route::put('payments/{payment}', [SubscriptionController::class, 'update'])->name('platform.payments.update');
        Route::delete('payments/{payment}', [SubscriptionController::class, 'destroy'])->name('platform.payments.destroy');

        Route::get('plans', [PlanController::class, 'index'])->name('platform.plans.index');
        Route::post('plans', [PlanController::class, 'store'])->name('platform.plans.store');
        Route::put('plans/{plan}', [PlanController::class, 'update'])->name('platform.plans.update');
        Route::patch('password', [PlatformController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('platform.password.update');
        Route::post('logout', [PlatformController::class, 'logout'])->name('platform.logout');
    });
});

/*
 * Where a suspended company lands. Outside the `{current_team}` prefix, because
 * the middleware that guards those routes is exactly what redirects here.
 */
Route::get('suspended', SuspendedController::class)
    ->middleware('auth')
    ->name('company.suspended');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        /*
         * Every route below names the permission it needs.
         *
         * The UI hides what a member cannot do, but a hidden button is still a
         * reachable URL — this is where the answer is actually decided. A
         * screen both viewers and managers reach names both permissions, and
         * any one of them is enough.
         */
        /*
         * `sales/invoices/create` is declared before `sales/invoices/{invoice}`
         * — otherwise the literal is matched as an invoice id and 404s.
         */
        Route::middleware(EnsureTeamPermission::class.':invoice:create')->group(function () {
            Route::get('sales/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
            Route::post('sales/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            // The live-stock poll belongs to whoever can write an invoice; it
            // is the invoice form asking whether its figures are still current.
            Route::get('sales/stock-version', [InvoiceController::class, 'stockVersion'])->name('stock.version');
        });

        Route::middleware(EnsureTeamPermission::class.':invoice:view')->group(function () {
            Route::get('sales/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('sales/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::get('sales/invoices/{invoice}/challan', [InvoiceController::class, 'challan'])->name('invoices.challan');
            // Named `excel`, not `export`: Wayfinder turns the last segment
            // into a TypeScript export name, and `export` is reserved there.
            Route::get('sales/invoices/{invoice}/excel', [InvoiceController::class, 'export'])
                ->middleware('throttle:30,1')
                ->name('invoices.excel');
        });

        Route::middleware(EnsureTeamPermission::class.':invoice:update')->group(function () {
            Route::get('sales/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
            Route::put('sales/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
            // Marking delivered moves stock and money, so it sits with editing
            // rather than with viewing.
            Route::patch('sales/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status.update');
        });

        Route::delete('sales/invoices/{invoice}', [InvoiceController::class, 'destroy'])
            ->middleware(EnsureTeamPermission::class.':invoice:delete')
            ->name('invoices.destroy');

        /*
         * Sales returns. `create` is declared before `{return}` for the same
         * reason invoices are — otherwise the literal is read as an id.
         */
        Route::middleware(EnsureTeamPermission::class.':return:manage')->group(function () {
            Route::get('sales/returns/create', [SalesReturnController::class, 'create'])->name('returns.create');
            Route::post('sales/returns', [SalesReturnController::class, 'store'])->name('returns.store');
        });

        Route::middleware(EnsureTeamPermission::class.':return:view,return:manage')->group(function () {
            Route::get('sales/returns', [SalesReturnController::class, 'index'])->name('returns.index');
            Route::get('sales/returns/{return}', [SalesReturnController::class, 'show'])->name('returns.show');
        });

        Route::middleware(EnsureTeamPermission::class.':return:manage')->group(function () {
            Route::get('sales/returns/{return}/edit', [SalesReturnController::class, 'edit'])->name('returns.edit');
            Route::put('sales/returns/{return}', [SalesReturnController::class, 'update'])->name('returns.update');
            Route::delete('sales/returns/{return}', [SalesReturnController::class, 'destroy'])->name('returns.destroy');
        });

        /*
         * The company's papers.
         *
         * `documents/create` is declared before `documents/{document}` for the
         * reason the products block spells out: registration order decides
         * matching, and a literal read as an id is a 500 on Postgres.
         */
        Route::middleware(EnsureTeamPermission::class.':document:manage')->group(function () {
            Route::get('documents/create', [DocumentController::class, 'create'])->name('documents.create');
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        });

        Route::middleware(EnsureTeamPermission::class.':document:view,document:manage')->group(function () {
            Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

            /*
             * The only way a stored file is ever read. Throttled because it
             * streams from disk, and named `download` rather than `file` so the
             * Wayfinder export reads as the verb it is.
             */
            Route::get('documents/{document}/download', [DocumentController::class, 'download'])
                ->middleware('throttle:60,1')
                ->name('documents.download');
        });

        Route::middleware(EnsureTeamPermission::class.':document:manage')->group(function () {
            Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
            Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        });

        /*
         * People.
         *
         * `employee:*` covers who works here; what they are paid is gated
         * separately on `payroll:*`, and no route in this block discloses a
         * figure. Departments ride on the employee permissions — the list is
         * only ever read while filling in an employee form.
         *
         * `hr/employees/create` and `hr/departments` are declared before
         * `hr/employees/{employee}` for the reason the products block spells
         * out: registration order decides matching, and a literal read as an id
         * is a 500 on Postgres.
         */
        Route::middleware(EnsureTeamPermission::class.':employee:manage')->group(function () {
            Route::get('hr/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
            Route::post('hr/employees', [EmployeeController::class, 'store'])->name('employees.store');
        });

        Route::middleware(EnsureTeamPermission::class.':employee:view,employee:manage')->group(function () {
            Route::get('hr/employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('hr/departments', [DepartmentController::class, 'index'])->name('departments.index');
            Route::get('hr/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        });

        Route::middleware(EnsureTeamPermission::class.':employee:manage')->group(function () {
            Route::post('hr/departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::put('hr/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('hr/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            Route::get('hr/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
            Route::put('hr/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::delete('hr/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        });

        /*
         * Attendance.
         *
         * Its own permission pair: a floor supervisor marks the grid every
         * morning and has no business in the registry or the payroll figures.
         * The working week and the holidays that shape it are managed by
         * whoever can correct attendance.
         */
        Route::middleware(EnsureTeamPermission::class.':attendance:view,attendance:manage')->group(function () {
            Route::get('hr/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('hr/attendance/summary', [AttendanceController::class, 'summary'])->name('attendance.summary');
            Route::get('hr/attendance/excel', [AttendanceController::class, 'excel'])
                ->middleware('throttle:30,1')
                ->name('attendance.excel');
            Route::get('hr/holidays', [HolidayController::class, 'index'])->name('holidays.index');
        });

        Route::middleware(EnsureTeamPermission::class.':attendance:manage')->group(function () {
            Route::put('hr/attendance', [AttendanceController::class, 'update'])->name('attendance.update');
            Route::post('hr/holidays', [HolidayController::class, 'store'])->name('holidays.store');
            Route::put('hr/holidays/settings', [HolidayController::class, 'updateSettings'])->name('holidays.settings.update');
            Route::delete('hr/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
        });

        /*
         * Payroll — the sensitive half of People.
         *
         * `payroll:view` is what opens a salary figure; `employee:view` does
         * not, so a supervisor with the registry and the attendance grid still
         * sees no money. Every literal is declared before `{run}` and
         * `{employee}` so it can never be read as an id.
         */
        Route::middleware(EnsureTeamPermission::class.':payroll:view,payroll:manage')->group(function () {
            Route::get('hr/payroll', [PayrollRunController::class, 'index'])->name('payroll.index');
            Route::get('hr/payroll/rates', [SalaryRateController::class, 'index'])->name('salary-rates.index');
            Route::get('hr/salary-payments', [SalaryPaymentController::class, 'index'])->name('salary-payments.index');
            Route::get('hr/bonuses', [BonusController::class, 'index'])->name('bonuses.index');
            Route::get('hr/payroll/{run}', [PayrollRunController::class, 'show'])->name('payroll.show');
            Route::get('hr/payroll/{run}/payslips', [PayslipController::class, 'index'])->name('payslips.index');
            Route::get('hr/employees/{employee}/statement', [PayslipController::class, 'statement'])->name('employees.statement');
        });

        Route::middleware(EnsureTeamPermission::class.':payroll:manage')->group(function () {
            Route::post('hr/payroll/open', [PayrollRunController::class, 'open'])->name('payroll.open');
            Route::post('hr/payroll/rates', [SalaryRateController::class, 'store'])->name('salary-rates.store');
            Route::delete('hr/payroll/rates/{rate}', [SalaryRateController::class, 'destroy'])->name('salary-rates.destroy');

            Route::get('hr/salary-payments/create', [SalaryPaymentController::class, 'create'])->name('salary-payments.create');
            Route::post('hr/salary-payments', [SalaryPaymentController::class, 'store'])->name('salary-payments.store');
            Route::delete('hr/salary-payments/{payment}', [SalaryPaymentController::class, 'destroy'])->name('salary-payments.destroy');

            Route::post('hr/bonuses', [BonusController::class, 'store'])->name('bonuses.store');
            Route::delete('hr/bonuses/{bonus}', [BonusController::class, 'destroy'])->name('bonuses.destroy');

            Route::put('hr/payroll/{run}', [PayrollRunController::class, 'update'])->name('payroll.update');
            Route::post('hr/payroll/{run}/approve', [PayrollRunController::class, 'approve'])->name('payroll.approve');
            Route::post('hr/payroll/{run}/reopen', [PayrollRunController::class, 'reopen'])->name('payroll.reopen');
        });

        Route::get('products', [ProductController::class, 'index'])
            ->middleware(EnsureTeamPermission::class.':product:view,product:manage')
            ->name('products.index');

        /*
         * The stock report lives with the products it counts, not with the
         * financial report, because it is the warehouse's sheet rather than the
         * accountant's. It still asks for `report:view`: it prices what was
         * sold, and that is a reporting figure whoever is reading it.
         *
         * Declared before `products/{product}/...` so `stock-report` is never
         * read as a product id.
         */
        Route::middleware(EnsureTeamPermission::class.':report:view')->group(function () {
            Route::get('products/stock-report', [StockReportController::class, 'index'])->name('stock-reports.index');
            Route::get('products/stock-report/excel', [StockReportController::class, 'excel'])
                ->middleware('throttle:30,1')
                ->name('stock-reports.excel');
        });

        Route::middleware(EnsureTeamPermission::class.':product:manage')->group(function () {
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
            // Adding production or writing goods off is repricing the shelf
            // rather than the price list, but it is the same act of authority.
            Route::post('products/{product}/stock', [StockMovementController::class, 'store'])->name('stock-movements.store');
        });

        // Declared before the `{material}` routes so `raw-materials/purchases`
        // can never be read as a material slug.
        Route::middleware(EnsureTeamPermission::class.':material:manage')->group(function () {
            Route::get('raw-materials/purchases/create', [MaterialPurchaseController::class, 'create'])->name('purchases.create');
            Route::post('raw-materials/purchases', [MaterialPurchaseController::class, 'store'])->name('purchases.store');
            Route::get('raw-materials/create', [RawMaterialController::class, 'create'])->name('materials.create');
            Route::post('raw-materials', [RawMaterialController::class, 'store'])->name('materials.store');
            Route::get('raw-materials/{material}/edit', [RawMaterialController::class, 'edit'])->name('materials.edit');
            Route::put('raw-materials/{material}', [RawMaterialController::class, 'update'])->name('materials.update');
        });

        Route::middleware(EnsureTeamPermission::class.':material:view,material:manage')->group(function () {
            Route::get('raw-materials/purchases', [MaterialPurchaseController::class, 'index'])->name('purchases.index');
            Route::get('raw-materials/purchases/{purchase}', [MaterialPurchaseController::class, 'show'])->name('purchases.show');
            Route::get('raw-materials/stock-levels', [StockLevelController::class, 'index'])->name('stock-levels.index');
            Route::get('raw-materials', [RawMaterialController::class, 'index'])->name('materials.index');
        });

        /*
         * `distributors/create` must be declared before `distributors/{distributor}`.
         *
         * Routes match in registration order, so the other way round the
         * literal `create` is read as a distributor id — which on Postgres is a
         * 500 (`invalid input syntax for type bigint`), not a 404. It reached
         * production that way once; the test in ReachableScreensTest opens
         * every create screen so it cannot again.
         */
        Route::middleware(EnsureTeamPermission::class.':distributor:manage')->group(function () {
            Route::get('distributors/create', [DistributorController::class, 'create'])->name('distributors.create');
            Route::post('distributors', [DistributorController::class, 'store'])->name('distributors.store');
        });

        Route::middleware(EnsureTeamPermission::class.':distributor:view,distributor:manage')->group(function () {
            Route::get('distributors', [DistributorController::class, 'index'])->name('distributors.index');
            Route::get('distributors/{distributor}', [DistributorController::class, 'show'])->name('distributors.show');
            Route::get('distributors/{distributor}/statement', [StatementController::class, 'print'])->name('statements.print');
            Route::get('distributors/{distributor}/statement/excel', [StatementController::class, 'excel'])
                ->middleware('throttle:30,1')
                ->name('statements.excel');
        });

        Route::middleware(EnsureTeamPermission::class.':distributor:manage')->group(function () {
            Route::get('distributors/{distributor}/edit', [DistributorController::class, 'edit'])->name('distributors.edit');
            Route::put('distributors/{distributor}', [DistributorController::class, 'update'])->name('distributors.update');
            Route::delete('distributors/{distributor}', [DistributorController::class, 'destroy'])->name('distributors.destroy');
        });

        Route::get('finance/payments', [PaymentController::class, 'index'])
            ->middleware(EnsureTeamPermission::class.':payment:view,payment:manage')
            ->name('payments.index');

        Route::middleware(EnsureTeamPermission::class.':payment:manage')->group(function () {
            // Declared before `{payment}` so the literal is never read as an id.
            Route::get('finance/payments/record', [PaymentController::class, 'record'])->name('payments.record');
            Route::get('distributors/{distributor}/payments/create', [PaymentController::class, 'create'])->name('payments.create');
            Route::post('finance/payments', [PaymentController::class, 'store'])->name('payments.store');
            Route::get('finance/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
            Route::put('finance/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
            Route::delete('finance/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        });

        // Declared before `{vendor}` so these literals can never be read as an id.
        Route::middleware(EnsureTeamPermission::class.':vendor:manage')->group(function () {
            Route::get('vendors/create', [VendorController::class, 'create'])->name('vendors.create');
            Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');

            Route::get('vendors/bills/create', [VendorBillController::class, 'create'])->name('bills.create');
            Route::post('vendors/bills', [VendorBillController::class, 'store'])->name('bills.store');
            Route::get('vendors/bills/{bill}/edit', [VendorBillController::class, 'edit'])->name('bills.edit');
            Route::put('vendors/bills/{bill}', [VendorBillController::class, 'update'])->name('bills.update');
            Route::delete('vendors/bills/{bill}', [VendorBillController::class, 'destroy'])->name('bills.destroy');

            Route::get('vendors/payments/create', [VendorPaymentController::class, 'create'])->name('vendor-payments.create');
            Route::post('vendors/payments', [VendorPaymentController::class, 'store'])->name('vendor-payments.store');
            Route::get('vendors/payments/{payment}/edit', [VendorPaymentController::class, 'edit'])->name('vendor-payments.edit');
            Route::put('vendors/payments/{payment}', [VendorPaymentController::class, 'update'])->name('vendor-payments.update');
            Route::delete('vendors/payments/{payment}', [VendorPaymentController::class, 'destroy'])->name('vendor-payments.destroy');
        });

        Route::middleware(EnsureTeamPermission::class.':vendor:view,vendor:manage')->group(function () {
            Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
            Route::get('vendors/bills', [VendorBillController::class, 'index'])->name('bills.index');
            Route::get('vendors/payments', [VendorPaymentController::class, 'index'])->name('vendor-payments.index');
            Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
        });

        Route::middleware(EnsureTeamPermission::class.':vendor:manage')->group(function () {
            Route::get('vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
            Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
            Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
        });

        Route::get('finance/expenses', [ExpenseController::class, 'index'])
            ->middleware(EnsureTeamPermission::class.':expense:view,expense:manage')
            ->name('expenses.index');

        Route::middleware(EnsureTeamPermission::class.':expense:manage')->group(function () {
            Route::get('finance/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
            Route::post('finance/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('finance/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
            Route::put('finance/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('finance/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        });

        Route::middleware(EnsureTeamPermission::class.':report:view')->group(function () {
            Route::get('finance/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('finance/reports/excel', [ReportController::class, 'excel'])
                ->middleware('throttle:30,1')
                ->name('reports.excel');

        });

        // Banks are the list a payment or expense picks from, so anyone who can
        // record either needs to read them; adding one is an admin act.
        Route::get('finance/banks', [BankController::class, 'index'])
            ->middleware(EnsureTeamPermission::class.':payment:manage,expense:manage,vendor:manage')
            ->name('banks.index');
        Route::post('finance/banks', [BankController::class, 'store'])
            ->middleware(EnsureTeamPermission::class.':payment:manage,expense:manage,vendor:manage')
            ->name('banks.store');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
