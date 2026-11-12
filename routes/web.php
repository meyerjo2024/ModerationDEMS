<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCommentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MySubjectsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Render / uptime health check — verifies the database is reachable.
Route::get('/health', function () {
    try {
        DB::select('select 1');

        return response()->json(['ok' => true]);
    } catch (Throwable) {
        return response()->json(['ok' => false], 503);
    }
});

Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/support', 'legal.support')->name('support');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [\App\Http\Controllers\RegisterController::class, 'show'])->name('register');
    Route::post('/register', [\App\Http\Controllers\RegisterController::class, 'store'])->middleware('throttle:6,10');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/password', [\App\Http\Controllers\AccountController::class, 'show'])->name('account.password');
    Route::post('/account/password', [\App\Http\Controllers\AccountController::class, 'update'])->name('account.password.update');
    Route::delete('/account', [\App\Http\Controllers\AccountController::class, 'destroy'])->name('account.destroy');
    Route::post('/devices', [\App\Http\Controllers\DeviceController::class, 'store'])->name('devices.store');
    Route::delete('/devices', [\App\Http\Controllers\DeviceController::class, 'destroy']);
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/my-subjects', [MySubjectsController::class, 'index'])->name('my-subjects');
    Route::put('/my-subjects', [MySubjectsController::class, 'update'])->middleware('role:EXAMINER,INTERNAL_MODERATOR')->name('my-subjects.update');
    Route::get('/assessments/create', [AssessmentController::class, 'create'])->middleware('role:EXAMINER')->name('assessments.create');
    Route::post('/assessments', [AssessmentController::class, 'store'])->middleware('role:EXAMINER')->name('assessments.store');
    Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');

    Route::prefix('/assessments/{assessment}')->controller(WorkflowController::class)->group(function () {
        Route::put('/section1', 'saveSection1')->middleware('role:EXAMINER');
        Route::post('/attachments', 'upload')->middleware('role:EXAMINER');
        Route::post('/submit-pre', 'submitPre')->middleware('role:EXAMINER');
        Route::post('/pre-review', 'preReview')->middleware('role:INTERNAL_MODERATOR');
        Route::get('/marks', 'marks')->middleware('role:EXAMINER');
        Route::post('/calculate', 'calculate')->middleware('role:EXAMINER');
        Route::post('/submit-post', 'submitPost')->middleware('role:EXAMINER');
        Route::post('/final-review', 'finalReview')->middleware('role:INTERNAL_MODERATOR,EXTERNAL_MODERATOR');
        Route::post('/section3-sign', 'section3Sign')->middleware('role:EXAMINER,HOD');
    });

    Route::get('/files/{attachment}', [FileController::class, 'show'])->name('files.show');
    Route::get('/attachments/{attachment}/comments', [DocumentCommentController::class, 'index']);
    Route::post('/attachments/{attachment}/comments', [DocumentCommentController::class, 'store']);
    Route::delete('/comments/{comment}', [DocumentCommentController::class, 'destroy']);
    Route::patch('/comments/{comment}', [DocumentCommentController::class, 'address']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);

    Route::middleware('role:HOD')->prefix('/overview')->group(function () {
        Route::get('/', [\App\Http\Controllers\OverviewController::class, 'index'])->name('overview');
        Route::get('/subjects/{subject}', [\App\Http\Controllers\OverviewController::class, 'subject'])->name('overview.subject');
    });

    Route::middleware('role:HOD,SYSTEM_ADMIN')->prefix('/admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users');
        Route::post('/users/import', [AdminController::class, 'importUsers'])->name('admin.users.import');
        Route::get('/users/template', [AdminController::class, 'usersTemplate'])->name('admin.users.template');
        Route::patch('/users/{user}/roles', [AdminController::class, 'updateRoles'])->name('admin.roles');
        Route::patch('/users/{user}/active', [AdminController::class, 'toggleActive'])->name('admin.active');
        Route::post('/registrations/{user}/approve', [AdminController::class, 'approve'])->name('admin.approve');
        Route::delete('/registrations/{user}', [AdminController::class, 'reject'])->name('admin.reject');
        Route::post('/subjects/import', [AdminController::class, 'importSubjects'])->name('admin.subjects.import');
        Route::post('/subjects', [AdminController::class, 'storeSubject'])->name('admin.subjects');
    });
});
