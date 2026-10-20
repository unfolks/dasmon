<?php

use App\Http\Controllers\AccessPointController;
use App\Http\Controllers\CctvController;
use App\Http\Controllers\ComputerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NasController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/api/v1/cctv/{site}', CctvController::class)->middleware('throttle:60,1')->name('cctv.index');
Route::get('/api/v1/nas/{site}', NasController::class)->middleware('throttle:60,1')->name('nas.index');
Route::get('/api/v1/computers/{site}', ComputerController::class)->middleware('throttle:60,1')->name('computers.index');
Route::get('/api/v1/access-points/{site}', AccessPointController::class)->middleware('throttle:60,1')->name('access-points.index');
