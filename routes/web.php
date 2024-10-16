<?php

use App\Http\Controllers\BimbinganController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () {
//     return Inertia::render('Home', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

Route::inertia('/', 'Home/Index', [
    'canLogin' => Route::has('login'),
])->name('home');
Route::inertia('/pedomans', 'Pedoman/Index', [
    'canLogin' => Route::has('login'),
])->name('pedomans.index');

Route::middleware('auth')->group(function () {
    Route::post('bimbingans/store', [BimbinganController::class, 'store'])->name('bimbingans.store');
    Route::resource('logbooks', LogbookController::class);
    Route::resource('laporans', LaporanController::class);
});

// Admin Page
// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

require __DIR__.'/auth.php';
