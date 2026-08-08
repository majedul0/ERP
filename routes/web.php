<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Distributors\DistributorController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Products\ProductController;
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
        Route::post('sales/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('sales/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::patch('sales/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status.update');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');

        Route::get('distributors', [DistributorController::class, 'index'])->name('distributors.index');
        Route::get('distributors/create', [DistributorController::class, 'create'])->name('distributors.create');
        Route::post('distributors', [DistributorController::class, 'store'])->name('distributors.store');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
