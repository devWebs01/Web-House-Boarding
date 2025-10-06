<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('pages.welcome');
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::get('/wa', function () {
    $fonnte = new \App\Services\FonnteService;

    $userPhone = '6285951572182'; // pastikan nomor aktif & terdaftar
    $message = 'WOIIIIIIIIIIIIIIIII Halo! Ini adalah pesan tes dari sistem Laravel.';
    $fonnte->send($userPhone, $message);

});

Route::get('/backup', function () {
    try {
        $output = shell_exec('php '.base_path('artisan').' backup:run 2>&1');

        if (! $output) {
            throw new \Exception('Perintah backup gagal atau tidak mengembalikan output.');
        }

    } catch (\Throwable $e) {
        return response()->make(
            "<h3 style='color: red;'>Terjadi kesalahan saat menjalankan backup:</h3><pre>{$e->getMessage()}</pre>",
            500
        );
    }

    return redirect()->back();

})->name('backup-system');

Route::get('/clean', function () {
    try {
        $output = shell_exec('php '.base_path('artisan').' backup:clean 2>&1');

        if (! $output) {
            throw new \Exception('Perintah clean gagal atau tidak mengembalikan output.');
        }

    } catch (\Throwable $e) {
        return response()->make(
            "<h3 style='color: red;'>Terjadi kesalahan saat menjalankan clean:</h3><pre>{$e->getMessage()}</pre>",
            500
        );
    }

    return redirect()->back();

})->name('backup-clean-all');

Route::get('/laravel/{file}', function ($file) {
    // Basic sanitization to prevent directory traversal attacks
    if (str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
        return redirect()->back()->with('error', 'Nama file tidak valid.');
    }

    $appName = config('backup.backup.name');
    $diskName = config('backup.backup.destination.disks')[0];

    // Get the full path from the storage disk configuration
    $fullPath = Illuminate\Support\Facades\Storage::disk($diskName)->path($appName.'/'.$file);

    // Normalize directory separators for Windows
    $fullPath = str_replace('/', DIRECTORY_SEPARATOR, $fullPath);

    if (file_exists($fullPath)) {
        // Use shell_exec with the 'del' command for Windows
        // The double quotes handle spaces in the path
        shell_exec('del "'.$fullPath.'"');

        // Short delay to allow the filesystem to update
        sleep(1);

        // Verify the file has been deleted
        if (! file_exists($fullPath)) {
            return redirect()->back()->with('success', 'Backup berhasil dihapus.');
        } else {
            return redirect()->back()->with('error', 'Gagal menghapus backup. Periksa izin file/folder.');
        }
    } else {
        return redirect()->back()->with('error', 'File backup tidak ditemukan.');
    }
})->name('backup.delete');

// Payment Routes
Route::post('/payment/create', [PaymentController::class, 'createPayment'])->name('payment.create');
Route::post('/payment/callback', [PaymentController::class, 'handleCallback'])->name('payment.callback');
Route::get('/payment/status/{orderId}', [PaymentController::class, 'getStatus'])->name('payment.status');
Route::post('/payment/cancel/{orderId}', [PaymentController::class, 'cancelTransaction'])->name('payment.cancel');
