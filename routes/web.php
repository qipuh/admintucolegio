<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TeacherAttendanceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('schools/sync', [SchoolController::class, 'syncAll'])->name('schools.sync-all');
        Route::resource('schools', SchoolController::class);
        Route::patch('schools/{school}/toggle-active', [SchoolController::class, 'toggleActive'])->name('schools.toggle-active');
        Route::post('schools/{school}/sync', [SchoolController::class, 'sync'])->name('schools.sync');
        Route::get('schools/{school}/asistencia-docente', [TeacherAttendanceController::class, 'index'])->name('schools.teacher-attendance');
        Route::get('schools/{school}/asistencia-docente/export', [TeacherAttendanceController::class, 'export'])->name('schools.teacher-attendance.export');

        Route::resource('support', SupportTicketController::class)
            ->parameters(['support' => 'ticket'])
            ->except(['edit']);
        Route::post('support/{ticket}/comments', [SupportTicketController::class, 'comment'])->name('support.comment');
    });
});

require __DIR__.'/auth.php';
