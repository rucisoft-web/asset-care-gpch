<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LabelPdfController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Logo & ikon perusahaan (Pengaturan › Identitas perusahaan); publik karena tampil di halaman login.
Route::get('/brand/{type}', [BrandAssetController::class, 'show'])->where('type', 'logo|logo-dark|favicon')->name('brand.asset');

// URL pada label QR. Tamu diarahkan ke login lalu kembali ke sini; token disimpan hanya dalam bentuk hash.
Route::get('/q/{token}', [QrController::class, 'show'])->where('token', '[A-Za-z0-9_-]{20,64}')
    ->middleware('throttle:60,1')->name('qr.show');

Route::middleware(['auth'])->group(function () {
    Route::get('/labels/{batch}/pdf', [LabelPdfController::class, 'show'])->name('labels.pdf');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/assets/{asset}/photo', [AttachmentController::class, 'assetPhoto'])->name('assets.photo');
    Route::get('/bantuan/{key}', [HelpController::class, 'show'])->where('key', '[a-z0-9.\-]+')->name('help.show');
    Route::get('/reports/{report}/export', [ReportExportController::class, 'export'])->name('reports.export');
});
