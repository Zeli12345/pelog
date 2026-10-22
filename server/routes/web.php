<?php

use App\Http\Controllers\ClientDownloadController;
use App\Http\Controllers\Dashboard\AuditController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DeviceController;
use App\Http\Controllers\Dashboard\DeviceNoteController;
use App\Http\Controllers\Dashboard\InventoryController;
use App\Http\Controllers\Dashboard\LocationController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\ScreenshotController;
use App\Http\Controllers\Dashboard\SessionController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\StaffController;
use App\Http\Controllers\Dashboard\StaffImportController;
use App\Http\Controllers\Dashboard\StudentController;
use App\Http\Controllers\Dashboard\StudentImportController;
use App\Http\Controllers\Dashboard\SubjectController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\ToolDownloadController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Unduhan installer client untuk agen pembaruan otomatis (diakses sebagai SYSTEM).
Route::get('/downloads/client-setup', [ClientDownloadController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('client.download');

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

    // Master data hanya-baca untuk semua pengguna dashboard (termasuk Viewer).
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');

    // Aset & lokasi: baca untuk semua (Viewer read-only).
    Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
    Route::get('/locations/{location}', [LocationController::class, 'show'])->whereNumber('location')->name('locations.show');
    Route::get('/locations/{location}/background', [LocationController::class, 'background'])->whereNumber('location')->name('locations.background');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{item}', [InventoryController::class, 'show'])->whereNumber('item')->name('inventory.show');
    Route::get('/inventory/{item}/photo', [InventoryController::class, 'photo'])->whereNumber('item')->name('inventory.photo');
    Route::get('/note-attachments/{attachment}', [DeviceNoteController::class, 'attachment'])->name('note-attachments.show');

    Route::middleware('admin')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

        // Perangkat: ubah nama/lokasi/status (mode perawatan)
        Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');

        // Perangkat: hapus/pulihkan (soft delete) + aksi massal. Menghapus
        // perangkat memberi sinyal self-wipe ke kiosk saat boot berikutnya.
        Route::post('/devices/bulk-delete', [DeviceController::class, 'bulkDestroy'])->name('devices.bulk-delete');
        Route::post('/devices/bulk-restore', [DeviceController::class, 'bulkRestore'])->name('devices.bulk-restore');
        Route::post('/devices/{device}/restore', [DeviceController::class, 'restore'])->whereNumber('device')->name('devices.restore');
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

        // Sesi: tutup paksa sesi aktif
        Route::post('/sessions/{session}/close', [SessionController::class, 'close'])->name('sessions.close');

        // Perangkat: minta screenshot langsung (diproses kiosk saat heartbeat berikutnya)
        Route::post('/devices/{device}/request-screenshot', [DeviceController::class, 'requestScreenshot'])->name('devices.request-screenshot');

        // Catatan perangkat
        Route::post('/devices/{device}/notes', [DeviceNoteController::class, 'store'])->name('devices.notes.store');
        Route::put('/devices/{device}/notes/{note}', [DeviceNoteController::class, 'update'])->name('devices.notes.update');
        Route::delete('/devices/{device}/notes/{note}', [DeviceNoteController::class, 'destroy'])->name('devices.notes.destroy');
        Route::delete('/devices/{device}/notes-attachments/{attachment}', [DeviceNoteController::class, 'destroyAttachment'])->name('devices.notes.attachments.destroy');

        // Lokasi & denah (tulis khusus Admin)
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::put('/locations/{location}', [LocationController::class, 'update'])->whereNumber('location')->name('locations.update');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->whereNumber('location')->name('locations.destroy');
        Route::post('/locations/{location}/merge', [LocationController::class, 'merge'])->whereNumber('location')->name('locations.merge');
        Route::put('/locations/{location}/layout', [LocationController::class, 'saveLayout'])->whereNumber('location')->name('locations.layout');
        Route::post('/locations/{location}/background', [LocationController::class, 'uploadBackground'])->whereNumber('location')->name('locations.background.upload');

        // Inventaris (tulis khusus Admin)
        Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::get('/inventory/{item}/edit', [InventoryController::class, 'edit'])->whereNumber('item')->name('inventory.edit');
        Route::put('/inventory/{item}', [InventoryController::class, 'update'])->whereNumber('item')->name('inventory.update');
        Route::post('/inventory/{item}/movements', [InventoryController::class, 'recordMovement'])->whereNumber('item')->name('inventory.movements.store');
        Route::post('/inventory/{item}/archive', [InventoryController::class, 'archive'])->whereNumber('item')->name('inventory.archive');
        Route::post('/inventory/{item}/restore', [InventoryController::class, 'restore'])->whereNumber('item')->name('inventory.restore');
        Route::post('/inventory/categories', [InventoryController::class, 'storeCategory'])->name('inventory.categories.store');
        Route::put('/inventory/categories/{category}', [InventoryController::class, 'updateCategory'])->whereNumber('category')->name('inventory.categories.update');
        Route::delete('/inventory/categories/{category}', [InventoryController::class, 'destroyCategory'])->whereNumber('category')->name('inventory.categories.destroy');

        // Siswa
        Route::get('/students/import', [StudentImportController::class, 'form'])->name('students.import.form');
        Route::post('/students/import', [StudentImportController::class, 'import'])->name('students.import');
        Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::post('/students/bulk-delete', [StudentController::class, 'bulkDestroy'])->name('students.bulk-delete');
        Route::post('/students/bulk-restore', [StudentController::class, 'bulkRestore'])->name('students.bulk-restore');
        Route::post('/students/{student}/restore', [StudentController::class, 'restore'])->whereNumber('student')->name('students.restore');
        Route::resource('students', StudentController::class)->except(['show', 'index']);

        // Guru / Pegawai
        Route::get('/staff/import', [StaffImportController::class, 'form'])->name('staff.import.form');
        Route::post('/staff/import', [StaffImportController::class, 'import'])->name('staff.import');
        Route::get('/staff/import/template', [StaffImportController::class, 'template'])->name('staff.import.template');
        Route::post('/staff/bulk-delete', [StaffController::class, 'bulkDestroy'])->name('staff.bulk-delete');
        Route::post('/staff/bulk-restore', [StaffController::class, 'bulkRestore'])->name('staff.bulk-restore');
        Route::post('/staff/{staff}/restore', [StaffController::class, 'restore'])->whereNumber('staff')->name('staff.restore');
        Route::resource('staff', StaffController::class)->except(['show', 'index']);

        // Mata pelajaran (rute baca ada di grup auth — bisa diakses Viewer)
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        // Pengaturan
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/enrollment-code', [SettingsController::class, 'generateEnrollmentCode'])->name('settings.enrollment-code');
        Route::post('/settings/client-installer', [SettingsController::class, 'uploadClientInstaller'])->name('settings.client-installer');

        // Pengguna dashboard (akun yang bisa login ke dasbor)
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

        // Alat perbaikan laptop (PELOG-Revert.exe) — khusus Admin.
        Route::get('/downloads/repair-tool', [ToolDownloadController::class, 'download'])->name('tool.download');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
