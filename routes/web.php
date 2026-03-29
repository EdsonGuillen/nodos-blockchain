<?php

use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\NodoController;
use App\Http\Controllers\TransaccionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return file_get_contents(public_path('index.html'));
});

Route::get('/chain', [BlockchainController::class, 'chain']);
Route::post('/mine', [BlockchainController::class, 'mine']);
Route::post('/blocks/receive', [BlockchainController::class, 'receiveBlock']);
Route::post('/block', [BlockchainController::class, 'receiveBlock']);
Route::post('/nodes/register', [NodoController::class, 'register']);
Route::get('/nodes/resolve', [NodoController::class, 'resolve']);
Route::post('/transactions', [TransaccionController::class, 'store']);
Route::post('/transactions/receive', [TransaccionController::class, 'receive']);
Route::get('/health', [BlockchainController::class, 'health']);
Route::get('/nodes',  [NodoController::class, 'listar']);