<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Distributors\DistributorController;
use App\Http\Controllers\Distributors\StatementController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\ReportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Payments\BankController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\RawMaterials\MaterialPurchaseController;
use App\Http\Controllers\RawMaterials\RawMaterialController;
use App\Http\Controllers\RawMaterials\StockLevelController;
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
        Route::patch('password', [PlatformController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('platform.password.update');
        Route::post('logout', [PlatformController::class, 'logout'])->name('platform.logout');
    });
});

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

        Route::get('products', [ProductController::class, 'index'])
            ->middleware(EnsureTeamPermission::class.':product:view,product:manage')
            ->name('products.index');

        Route::middleware(EnsureTeamPermission::class.':product:manage')->group(function () {
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
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

        Route::middleware(EnsureTeamPermission::class.':distributor:view,distributor:manage')->group(function () {
            Route::get('distributors', [DistributorController::class, 'index'])->name('distributors.index');
            Route::get('distributors/{distributor}', [DistributorController::class, 'show'])->name('distributors.show');
            Route::get('distributors/{distributor}/statement', [StatementController::class, 'print'])->name('statements.print');
            Route::get('distributors/{distributor}/statement/excel', [StatementController::class, 'excel'])
                ->middleware('throttle:30,1')
                ->name('statements.excel');
        });

        Route::middleware(EnsureTeamPermission::class.':distributor:manage')->group(function () {
            Route::get('distributors/create', [DistributorController::class, 'create'])->name('distributors.create');
            Route::post('distributors', [DistributorController::class, 'store'])->name('distributors.store');
            Route::get('distributors/{distributor}/edit', [DistributorController::class, 'edit'])->name('distributors.edit');
            Route::put('distributors/{distributor}', [DistributorController::class, 'update'])->name('distributors.update');
            Route::delete('distributors/{distributor}', [DistributorController::class, 'destroy'])->name('distributors.destroy');
        });

        Route::get('finance/payments', [PaymentController::class, 'index'])
            ->middleware(EnsureTeamPermission::class.':payment:view,payment:manage')
            ->name('payments.index');

        Route::middleware(EnsureTeamPermission::class.':payment:manage')->group(function () {
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
