<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Distributors\DistributorController;
use App\Http\Controllers\Distributors\StatementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Payments\BankController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\RawMaterials\MaterialPurchaseController;
use App\Http\Controllers\RawMaterials\RawMaterialController;
use App\Http\Controllers\RawMaterials\StockLevelController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('sales/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('sales/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::get('sales/stock-version', [InvoiceController::class, 'stockVersion'])->name('stock.version');
        Route::post('sales/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('sales/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('sales/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('sales/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::get('sales/invoices/{invoice}/challan', [InvoiceController::class, 'challan'])->name('invoices.challan');
        // Named `excel`, not `export`: Wayfinder turns the last segment into a
        // TypeScript export name, and `export` is a reserved word there.
        Route::get('sales/invoices/{invoice}/excel', [InvoiceController::class, 'export'])
            ->middleware('throttle:30,1')
            ->name('invoices.excel');
        Route::patch('sales/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status.update');
        Route::delete('sales/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');

        // Declared before the `{material}` routes so `raw-materials/purchases`
        // can never be read as a material slug.
        Route::get('raw-materials/purchases', [MaterialPurchaseController::class, 'index'])->name('purchases.index');
        Route::get('raw-materials/purchases/create', [MaterialPurchaseController::class, 'create'])->name('purchases.create');
        Route::post('raw-materials/purchases', [MaterialPurchaseController::class, 'store'])->name('purchases.store');
        Route::get('raw-materials/purchases/{purchase}', [MaterialPurchaseController::class, 'show'])->name('purchases.show');

        Route::get('raw-materials/stock-levels', [StockLevelController::class, 'index'])->name('stock-levels.index');

        Route::get('raw-materials', [RawMaterialController::class, 'index'])->name('materials.index');
        Route::get('raw-materials/create', [RawMaterialController::class, 'create'])->name('materials.create');
        Route::post('raw-materials', [RawMaterialController::class, 'store'])->name('materials.store');
        Route::get('raw-materials/{material}/edit', [RawMaterialController::class, 'edit'])->name('materials.edit');
        Route::put('raw-materials/{material}', [RawMaterialController::class, 'update'])->name('materials.update');

        Route::get('distributors', [DistributorController::class, 'index'])->name('distributors.index');
        Route::get('distributors/create', [DistributorController::class, 'create'])->name('distributors.create');
        Route::post('distributors', [DistributorController::class, 'store'])->name('distributors.store');
        Route::get('distributors/{distributor}', [DistributorController::class, 'show'])->name('distributors.show');
        Route::get('distributors/{distributor}/statement', [StatementController::class, 'print'])->name('statements.print');
        // Named `excel`, not `export`: Wayfinder turns the last segment into a
        // TypeScript export name, and `export` is a reserved word there.
        Route::get('distributors/{distributor}/statement/excel', [StatementController::class, 'excel'])
            ->middleware('throttle:30,1')
            ->name('statements.excel');
        Route::get('distributors/{distributor}/edit', [DistributorController::class, 'edit'])->name('distributors.edit');
        Route::put('distributors/{distributor}', [DistributorController::class, 'update'])->name('distributors.update');
        Route::delete('distributors/{distributor}', [DistributorController::class, 'destroy'])->name('distributors.destroy');
        Route::get('distributors/{distributor}/payments/create', [PaymentController::class, 'create'])->name('payments.create');

        Route::get('finance/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('finance/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('finance/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('finance/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
        Route::delete('finance/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

        Route::get('finance/banks', [BankController::class, 'index'])->name('banks.index');
        Route::post('finance/banks', [BankController::class, 'store'])->name('banks.store');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
