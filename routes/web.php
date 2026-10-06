<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamContextController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'permission:dashboard.view'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/data-konsumen', 'modules.placeholder', [
        'section' => 'Data Konsumen',
        'title' => 'Data Konsumen',
        'description' => 'Wadah data identitas konsumen dan metode pembayaran.',
    ])->middleware('permission:consumers.view')->name('consumers.index');

    Route::prefix('proses-penjualan')->name('sales-process.')->middleware('permission:sales-process.view')->group(function () {
        Route::view('/psjb-utj', 'modules.placeholder', ['section' => 'Proses Penjualan', 'title' => 'PSJB / UTJ', 'description' => 'Wadah proses pemesanan dan uang tanda jadi.'])->name('psjb-utj');
        Route::view('/bi-checking', 'modules.placeholder', ['section' => 'Proses Penjualan', 'title' => 'BI Checking', 'description' => 'Wadah pemeriksaan kelayakan awal konsumen.'])->name('bi-checking');
        Route::view('/pemberkasan', 'modules.placeholder', ['section' => 'Proses Penjualan', 'title' => 'Pemberkasan', 'description' => 'Wadah kelengkapan data dan berkas pengajuan.'])->name('pemberkasan');
        Route::view('/proses-bank', 'modules.placeholder', ['section' => 'Proses Penjualan', 'title' => 'Proses Bank', 'description' => 'Wadah perkembangan pengajuan pada bank.'])->name('bank');
        Route::view('/ppjb-developer', 'modules.placeholder', ['section' => 'Proses Penjualan', 'title' => 'PPJB Developer', 'description' => 'Wadah proses PPJB antara konsumen dan developer.'])->name('ppjb-developer');
        Route::view('/akad-bast', 'modules.placeholder', ['section' => 'Proses Penjualan', 'title' => 'Akad & BAST', 'description' => 'Wadah akad kredit dan berita acara serah terima.'])->name('akad-bast');
    });

    Route::view('/garansi', 'modules.placeholder', [
        'section' => 'Layanan',
        'title' => 'Garansi',
        'description' => 'Wadah pencatatan garansi di luar alur proses penjualan.',
    ])->middleware('permission:warranties.view')->name('warranties.index');
});

Route::middleware('auth')->group(function () {
    Route::post('/team-context/{team}', TeamContextController::class)->name('team-context.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::view('/branches', 'modules.placeholder', ['section' => 'Pengaturan', 'title' => 'Cabang', 'description' => 'Wadah pengaturan cabang perusahaan.'])->middleware('permission:branches.view')->name('branches.index');
    Route::view('/projects', 'modules.placeholder', ['section' => 'Pengaturan', 'title' => 'Proyek', 'description' => 'Wadah pengaturan proyek perumahan.'])->middleware('permission:projects.view')->name('projects.index');
    Route::view('/lots', 'modules.placeholder', ['section' => 'Pengaturan', 'title' => 'Kavling', 'description' => 'Wadah master kavling dan unit pada setiap proyek.'])->middleware('permission:lots.view')->name('lots.index');
    Route::view('/banks', 'modules.placeholder', ['section' => 'Pengaturan', 'title' => 'Bank', 'description' => 'Wadah master bank untuk proses pembiayaan.'])->middleware('permission:banks.view')->name('banks.index');
    Route::view('/promotions', 'modules.placeholder', ['section' => 'Pengaturan', 'title' => 'Promo', 'description' => 'Wadah pengaturan program promo penjualan.'])->middleware('permission:promotions.view')->name('promotions.index');

    Route::get('/teams', [TeamController::class, 'index'])->middleware('permission:teams.view')->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->middleware('permission:teams.create')->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->middleware('permission:teams.update')->name('teams.edit');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

require __DIR__.'/auth.php';
