<?php

use Illuminate\Support\Facades\Route;

// Dans routes/web.php, remplacez la route actuelle par :
Route::get('/', function () {
    return redirect()->route('login'); 
});

Route::middleware(['auth', 'verified', 'throttle:120,1'])->group(function () {

    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard.index');

    Route::livewire('/categories', 'pages::categories.index')->name('categories.index');

    Route::livewire('/products', 'pages::products.index')->name('products.index');

    Route::livewire('/suppliers', 'pages::suppliers.index')->name('suppliers.index');

    Route::livewire('/customers', 'pages::customers.index')->name('customers.index');

    Route::livewire('/orders', 'pages::orders.index')->name('orders.index');

    Route::livewire('/purchases', 'pages::purchases.index')->name('purchases.index');

    Route::livewire('/stock_movements', 'pages::stock-movements.index')->name('stock-movements.index');
});

require __DIR__.'/settings.php';
