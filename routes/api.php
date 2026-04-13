<?php

use App\Http\Controllers\AttendanceIngestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/attendance/ingest', [AttendanceIngestController::class, 'store']);
