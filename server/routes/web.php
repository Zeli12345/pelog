<?php

use App\Http\Controllers\Dashboard\AuditController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DeviceController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\ScreenshotController;
use App\Http\Controllers\Dashboard\SessionController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\StaffController;
use App\Http\Controllers\Dashboard\StaffImportController;
use App\Http\Controllers\Dashboard\StudentController;
use App\Http\Controllers\Dashboard\StudentImportController;
use App\Http\Controllers\Dashboard\SubjectController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/screenshots', [ScreenshotController::class, 'index'])->name('screenshots.index');
    Route::get('/screenshots/{screenshot}/thumb', [ScreenshotController::class, 'thumb'])->name('screenshots.thumb');
    Route::get('/screenshots/{screenshot}/file', [ScreenshotController::class, 'file'])->name('screenshots.file');

    Route::middleware('admin')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

        // Siswa
        Route::get('/students/import', [StudentImportController::class, 'form'])->name('students.import.form');
        Route::post('/students/import', [StudentImportController::class, 'import'])->name('students.import');
        Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::post('/students/{student}/reset-pin', [StudentController::class, 'resetPin'])->name('students.reset-pin');
        Route::resource('students', StudentController::class)->except(['show']);

        // Guru / Pegawai
        Route::get('/staff/import', [StaffImportController::class, 'form'])->name('staff.import.form');
        Route::post('/staff/import', [StaffImportController::class, 'import'])->name('staff.import');
        Route::get('/staff/import/template', [StaffImportController::class, 'template'])->name('staff.import.template');
        Route::resource('staff', StaffController::class)->except(['show']);

        // Mata pelajaran
        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        // Pengaturan
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/enrollment-code', [SettingsController::class, 'generateEnrollmentCode'])->name('settings.enrollment-code');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
