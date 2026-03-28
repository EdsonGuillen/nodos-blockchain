<?php

use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\NodoController;
use App\Http\Controllers\TransaccionController;
use Illuminate\Support\Facades\Route;

Route::get('/chain', [BlockchainController::class, 'chain']);
Route::post('/mine', [BlockchainController::class, 'mine']);
Route::post('/nodes/register', [NodoController::class, 'register']);
Route::get('/nodes/resolve', [NodoController::class, 'resolve']);
Route::post('/transactions', [TransaccionController::class, 'store']);
Route::post('/transactions/receive', [TransaccionController::class, 'receive']);
Route::post('/block', [App\Http\Controllers\BlockchainController::class, 'receiveBlock']);