<?php

use App\Http\Controllers\AskController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\FileScanController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::redirect('/cek', '/links', 301);
Route::get('/tanya', [PageController::class, 'tanya'])->name('tanya');
Route::get('/links', [PageController::class, 'links'])->name('links');
Route::get('/links/report/{urlId}', [PageController::class, 'linkReport'])
    ->where('urlId', '[A-Za-z0-9_-]+')->name('links.report');
Route::get('/files', [PageController::class, 'files'])->name('files');
Route::get('/files/report/{fileHash}', [PageController::class, 'fileReport'])
    ->where('fileHash', '[A-Fa-f0-9]{64}')->name('files.report');

Route::post('/api/links', [CheckController::class, 'store'])->name('api.links');
Route::post('/api/links/analysis', [CheckController::class, 'analysis'])->name('api.links.analysis');
Route::post('/api/files', [FileScanController::class, 'store'])->name('api.files');
Route::post('/api/files/analysis', [FileScanController::class, 'analysis'])->name('api.files.analysis');
Route::post('/api/tanya', [AskController::class, 'store'])->name('api.tanya');
