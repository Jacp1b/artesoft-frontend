<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\StoreController;

Route::get('/', [StoreController::class, 'index']);
Route::any('/api/index.php', [ApiController::class, 'handle']);
