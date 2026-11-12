<?php

use App\Http\Controllers\Admin\EvaluationFormController;
use App\Http\Controllers\Admin\EvaluationPeriodController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SchoolYearController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\StrandController;
use App\Http\Controllers\Admin\TeacherAssignmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Faculty\ResultController;
use App\Http\Controllers\Student\EvaluationController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::get('/', function (Request $request): RedirectResponse {
    return redirect()->route($request->user() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::middleware('can:users.manage')->group(function (): void {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    });
    Route::resource('school-years', SchoolYearController::class)->only(['store', 'update', 'destroy'])->middleware('can:strands.manage');
    Route::resource('sections', SectionController::class)->only(['store', 'update', 'destroy'])->middleware('can:strands.manage');
    Route::resource('strands', StrandController::class)->only(['index', 'store', 'update'])->middleware('can:strands.manage');
    Route::resource('assignments', TeacherAssignmentController::class)->only(['index', 'store', 'update'])->middleware('can:assignments.manage');
    Route::patch('assignments/{assignment}/status', [TeacherAssignmentController::class, 'status'])->name('assignments.status')->middleware('can:assignments.manage');
    Route::resource('forms', EvaluationFormController::class)->only(['index', 'store', 'update'])->middleware('can:forms.manage');
    Route::patch('forms/{form}/status', [EvaluationFormController::class, 'status'])->name('forms.status')->middleware('can:forms.manage');
    Route::resource('periods', EvaluationPeriodController::class)->only(['index', 'store', 'update'])->middleware('can:periods.manage');
    Route::post('periods/{period}/transition', [EvaluationPeriodController::class, 'transition'])->name('periods.transition')->middleware('can:periods.manage');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('can:results.view-all');
    Route::get('reports/download', [ReportController::class, 'download'])->name('reports.download')->middleware('can:reports.download');
    Route::get('evaluations', [EvaluationController::class, 'index'])->name('evaluations.index')->middleware(PermissionMiddleware::using(['evaluations.submit', 'evaluations.monitor']));
    Route::get('evaluations/{period}/teachers/{assignment}', [EvaluationController::class, 'create'])->name('evaluations.create')->middleware(PermissionMiddleware::using(['evaluations.submit', 'evaluations.monitor']));
    Route::post('evaluations/{period}', [EvaluationController::class, 'store'])->name('evaluations.store')->middleware('can:evaluations.submit');
    Route::get('results', [ResultController::class, 'index'])->name('results.index')->middleware(PermissionMiddleware::using(['results.view-own', 'results.view-all']));
});

require __DIR__.'/settings.php';
