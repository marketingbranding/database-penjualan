<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\LeadTimeController;
use App\Http\Controllers\Admin\LotController;
use App\Http\Controllers\Admin\LotHistoryController;
use App\Http\Controllers\Admin\ProjectBlockController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SpatialWorkspaceController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BiCheckingController;
use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PsjbUtjController;
use App\Http\Controllers\SalesAgendaController;
use App\Http\Controllers\SalesCaseChangeController;
use App\Http\Controllers\SalesCaseStatusController;
use App\Http\Controllers\SalesFeeReportController;
use App\Http\Controllers\SalesProcessController;
use App\Http\Controllers\TeamContextController;
use App\Http\Controllers\TeamPerformanceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/search', GlobalSearchController::class)->name('search');
    Route::get('/data-konsumen', [ConsumerController::class, 'index'])->name('consumers.index');
    Route::get('/data-konsumen/export', [ConsumerController::class, 'export'])->name('consumers.export');
    Route::get('/data-konsumen/create', [ConsumerController::class, 'create'])->name('consumers.create');
    Route::post('/data-konsumen', [ConsumerController::class, 'store'])->name('consumers.store');
    Route::get('/data-konsumen/{salesCase}/edit', [ConsumerController::class, 'edit'])->name('consumers.edit');
    Route::get('/data-konsumen/{salesCase}', [ConsumerController::class, 'show'])->name('consumers.show');
    Route::put('/data-konsumen/{salesCase}', [ConsumerController::class, 'update'])->name('consumers.update');
    Route::patch('/data-konsumen/{salesCase}/cancel', [ConsumerController::class, 'cancel'])->name('consumers.cancel');
    Route::delete('/data-konsumen/{salesCase}', [ConsumerController::class, 'destroy'])->name('consumers.destroy');

    Route::get('/leads/monitoring', [LeadController::class, 'monitoring'])->name('leads.monitoring');
    Route::post('/leads/{lead}/activities', [LeadController::class, 'storeActivity'])->name('leads.activities.store');
    Route::patch('/leads/{lead}/follow-up', [LeadController::class, 'updateFollowUp'])->name('leads.follow-up.update');
    Route::patch('/leads/{lead}/qualification', [LeadController::class, 'updateQualification'])->name('leads.qualification.update');
    Route::post('/leads/{lead}/handoffs', [LeadController::class, 'storeHandoff'])->name('leads.handoffs.store');
    Route::patch('/lead-handoffs/{leadHandoff}', [LeadController::class, 'reviewHandoff'])->name('lead-handoffs.review');
    Route::resource('leads', LeadController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('/sales-fee-reports/print', [SalesFeeReportController::class, 'print'])->name('sales-fee-reports.print');
    Route::get('/sales-fee-reports/pdf', [SalesFeeReportController::class, 'pdf'])->name('sales-fee-reports.pdf');
    Route::get('/sales-fee-reports/export', [SalesFeeReportController::class, 'export'])->name('sales-fee-reports.export');
    Route::get('/sales-fee-reports', [SalesFeeReportController::class, 'index'])->name('sales-fee-reports.index');
    Route::get('/sales-fee-reports/{salesUser}/pdf', [SalesFeeReportController::class, 'detailPdf'])->name('sales-fee-reports.detail-pdf');
    Route::get('/sales-fee-reports/{salesUser}/{project}/pdf', [SalesFeeReportController::class, 'detailPdf'])->name('sales-fee-reports.detail-pdf.project');
    Route::get('/sales-fee-reports/{salesUser}/{project?}', [SalesFeeReportController::class, 'show'])->name('sales-fee-reports.show');
    Route::get('/team-performance', [TeamPerformanceController::class, 'index'])->name('team-performance.index');
    Route::get('/team-performance/export', [TeamPerformanceController::class, 'export'])->name('team-performance.export');

    Route::prefix('proses-penjualan')->name('sales-process.')->middleware('permission:sales-process.view')->group(function () {
        Route::get('/psjb-utj', [PsjbUtjController::class, 'index'])->middleware('permission:psjb-utj.view')->name('psjb-utj');
        Route::get('/psjb-utj/create', [PsjbUtjController::class, 'create'])->middleware('permission:psjb-utj.create')->name('psjb-utj.create');
        Route::post('/psjb-utj', [PsjbUtjController::class, 'store'])->name('psjb-utj.store');
        Route::get('/psjb-utj/{psjbUtj}/edit', [PsjbUtjController::class, 'edit'])->middleware('permission:psjb-utj.update')->name('psjb-utj.edit');
        Route::put('/psjb-utj/{psjbUtj}', [PsjbUtjController::class, 'update'])->name('psjb-utj.update');
        Route::get('/psjb-utj/{psjbUtj}/change', [SalesCaseChangeController::class, 'create'])->middleware('permission:psjb-utj.update')->name('psjb-utj.change.create');
        Route::post('/psjb-utj/{psjbUtj}/change', [SalesCaseChangeController::class, 'store'])->name('psjb-utj.change.store');
        Route::get('/bi-checking', [BiCheckingController::class, 'index'])->middleware('permission:bi-checking.view')->name('bi-checking');
        Route::get('/bi-checking/create', [BiCheckingController::class, 'create'])->middleware('permission:bi-checking.create')->name('bi-checking.create');
        Route::post('/bi-checking', [BiCheckingController::class, 'store'])->name('bi-checking.store');
        Route::get('/bi-checking/{biChecking}/edit', [BiCheckingController::class, 'edit'])->middleware('permission:bi-checking.update')->name('bi-checking.edit');
        Route::put('/bi-checking/{biChecking}', [BiCheckingController::class, 'update'])->name('bi-checking.update');
        Route::patch('/sales-cases/{salesCase}/withdraw', [SalesCaseStatusController::class, 'withdraw'])->name('sales-cases.withdraw');
        Route::get('/pemberkasan', [SalesProcessController::class, 'filingIndex'])->middleware('permission:filing.view')->name('pemberkasan');
        Route::post('/pemberkasan', [SalesProcessController::class, 'filingStore'])->name('pemberkasan.store');
        Route::patch('/pemberkasan/{filing}', [SalesProcessController::class, 'filingUpdate'])->name('pemberkasan.update');
        Route::get('/proses-bank', [SalesProcessController::class, 'bankIndex'])->middleware('permission:bank-process.view')->name('bank');
        Route::post('/proses-bank', [SalesProcessController::class, 'bankStore'])->name('bank.store');
        Route::patch('/proses-bank/{bankProcess}', [SalesProcessController::class, 'bankUpdate'])->name('bank.update');
        Route::get('/ppjb-developer', [SalesProcessController::class, 'ppjbIndex'])->middleware('permission:ppjb.view')->name('ppjb-developer');
        Route::post('/ppjb-developer', [SalesProcessController::class, 'ppjbStore'])->name('ppjb-developer.store');
        Route::patch('/ppjb-developer/{ppjbDeveloperCase}', [SalesProcessController::class, 'ppjbUpdate'])->name('ppjb-developer.update');
        Route::get('/akad-bast', [SalesProcessController::class, 'akadIndex'])->middleware('permission:akad.view')->name('akad-bast');
        Route::post('/akad-bast', [SalesProcessController::class, 'akadStore'])->name('akad.store');
        Route::patch('/akad-bast/akad/{akadCase}', [SalesProcessController::class, 'akadUpdate'])->name('akad.update');
        Route::post('/akad-bast/bast', [SalesProcessController::class, 'bastStore'])->middleware('permission:bast.create')->name('bast.store');
        Route::patch('/akad-bast/bast/{bastCase}', [SalesProcessController::class, 'bastUpdate'])->middleware('permission:bast.update')->name('bast.update');
    });

    Route::get('/garansi', [SalesProcessController::class, 'warrantyIndex'])->middleware('permission:warranties.view')->name('warranties.index');
    Route::post('/garansi', [SalesProcessController::class, 'warrantyStore'])->name('warranties.store');
    Route::patch('/garansi/{warranty}', [SalesProcessController::class, 'warrantyUpdate'])->name('warranties.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
    Route::post('/team-context/{team}', TeamContextController::class)->name('team-context.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/agenda-sales', [SalesAgendaController::class, 'index'])->name('sales-agendas.index');
    Route::get('/agenda-sales/create', [SalesAgendaController::class, 'create'])->name('sales-agendas.create');
    Route::post('/agenda-sales', [SalesAgendaController::class, 'store'])->name('sales-agendas.store');
    Route::get('/agenda-sales/{salesAgenda}', [SalesAgendaController::class, 'show'])->name('sales-agendas.show');
    Route::get('/agenda-sales/{salesAgenda}/edit', [SalesAgendaController::class, 'edit'])->name('sales-agendas.edit');
    Route::get('/agenda-sales/{salesAgenda}/redirect', [SalesAgendaController::class, 'redirectForm'])->name('sales-agendas.redirect-form');
    Route::put('/agenda-sales/{salesAgenda}', [SalesAgendaController::class, 'update'])->name('sales-agendas.update');
    Route::patch('/agenda-sales/{salesAgenda}/redirect', [SalesAgendaController::class, 'redirect'])->name('sales-agendas.redirect');
    Route::patch('/agenda-sales/{salesAgenda}/cancel-by-coordinator', [SalesAgendaController::class, 'cancelAsCoordinator'])->name('sales-agendas.cancel-by-coordinator');
    Route::patch('/agenda-sales/{salesAgenda}/complete', [SalesAgendaController::class, 'complete'])->name('sales-agendas.complete');
    Route::patch('/agenda-sales/{salesAgenda}/cancel', [SalesAgendaController::class, 'cancel'])->name('sales-agendas.cancel');
    Route::delete('/agenda-sales/{salesAgenda}', [SalesAgendaController::class, 'destroy'])->name('sales-agendas.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/spatial-workspace', SpatialWorkspaceController::class)
        ->middleware('permission:spatial-workspace.view')
        ->name('spatial-workspace');
    Route::patch('/spatial-workspace/users/{user}', [SpatialWorkspaceController::class, 'update'])
        ->middleware('permission:users.assign-roles')
        ->name('spatial-workspace.users.update');

    Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:branches.view')->name('branches.index');
    Route::get('/branches/create', [BranchController::class, 'create'])->middleware('permission:branches.create')->name('branches.create');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->middleware('permission:branches.update')->name('branches.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

    Route::get('/projects', [ProjectController::class, 'index'])->middleware('permission:projects.view')->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('permission:projects.create')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->middleware('permission:projects.update')->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/projects/{project}/blocks', [ProjectBlockController::class, 'store'])->name('projects.blocks.store');
    Route::delete('/projects/{project}/blocks/{block}', [ProjectBlockController::class, 'destroy'])->name('projects.blocks.destroy');

    Route::get('/lots', [LotController::class, 'index'])->middleware('permission:lots.view')->name('lots.index');
    Route::patch('/lots/{lot}', [LotController::class, 'update'])->name('lots.update');
    Route::get('/lots/{lot}/history', LotHistoryController::class)->name('lots.history');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/lead-times', [LeadTimeController::class, 'index'])->middleware('permission:lead-time.view')->name('lead-times.index');
    Route::patch('/lead-times/{leadTimeTarget}', [LeadTimeController::class, 'update'])->name('lead-times.update');

    Route::get('/banks', [BankController::class, 'index'])->middleware('permission:banks.view')->name('banks.index');
    Route::post('/banks', [BankController::class, 'store'])->name('banks.store');
    Route::patch('/banks/{bank}', [BankController::class, 'update'])->name('banks.update');
    Route::delete('/banks/{bank}', [BankController::class, 'destroy'])->name('banks.destroy');
    Route::post('/banks/{bank}/units', [BankController::class, 'storeUnit'])->name('banks.units.store');
    Route::patch('/bank-units/{bankUnit}', [BankController::class, 'updateUnit'])->name('bank-units.update');
    Route::delete('/bank-units/{bankUnit}', [BankController::class, 'destroyUnit'])->name('bank-units.destroy');

    Route::get('/promotions', [PromotionController::class, 'index'])->middleware('permission:promotions.view')->name('promotions.index');
    Route::get('/promotions/create', [PromotionController::class, 'create'])->middleware('permission:promotions.create')->name('promotions.create');
    Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::get('/promotions/{promotion}/edit', [PromotionController::class, 'edit'])->middleware('permission:promotions.update')->name('promotions.edit');
    Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::patch('/promotions/{promotion}/toggle-active', [PromotionController::class, 'toggleActive'])->name('promotions.toggle-active');
    Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');

    Route::get('/teams', [TeamController::class, 'index'])->middleware('permission:teams.view')->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->middleware('permission:teams.create')->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->middleware('permission:teams.update')->name('teams.edit');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::post('/users/bulk', [UserController::class, 'bulk'])->name('users.bulk');
    Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
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
