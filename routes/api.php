<?php

use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\NodoController;
use App\Http\Controllers\TransaccionController;
use Illuminate\Support\Facades\Route;

// ── Cadena y minado ───────────────────────────────────────────────────────────
Route::get('/chain',  [BlockchainController::class, 'chain']);
Route::post('/mine',  [BlockchainController::class, 'mine']);

// ── Recepción de bloques — todos los endpoints que prueba el nodo Express ─────
Route::post('/blocks/receive', [BlockchainController::class, 'receiveBlock']);
Route::post('/block',          [BlockchainController::class, 'receiveBlock']);
Route::post('/receive',        [BlockchainController::class, 'receiveBlock']);
Route::post('/blocks',         [BlockchainController::class, 'receiveBlock']);
Route::post('/chain/receive',  [BlockchainController::class, 'receiveBlock']);
Route::post('/receive-block',  [BlockchainController::class, 'receiveBlock']);

// ── Nodos ─────────────────────────────────────────────────────────────────────
Route::post('/nodes/register', [NodoController::class, 'register']);
Route::get('/nodes/resolve',   [NodoController::class, 'resolve']);
Route::get('/nodes',           [NodoController::class, 'listar']);

// ── Transacciones ─────────────────────────────────────────────────────────────
Route::post('/transactions',         [TransaccionController::class, 'store']);
Route::post('/transactions/receive', [TransaccionController::class, 'receive']);
Route::get('/health', [BlockchainController::class, 'health']);
Route::get('/nodes',  [NodoController::class, 'listar']);

// Alias para que Express encuentre los bloques bajo /api también
Route::post('/blocks/receive', [BlockchainController::class, 'receiveBlock']);
Route::post('/block',          [BlockchainController::class, 'receiveBlock']);
Route::post('/receive',        [BlockchainController::class, 'receiveBlock']);