<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Nodo Blockchain API - Grados Académicos",
 *     version="1.0.0",
 *     description="API REST para nodo de red blockchain distribuida. Gestiona grados académicos como bloques en una cadena inmutable."
 * )
 * @OA\Server(
 *     url="http://10.158.86.29:8004",
 *     description="Nodo Laravel - ZeroTier"
 * )
 * @OA\Server(
 *     url="http://localhost:8004",
 *     description="Nodo Laravel - Local"
 * )
 */
abstract class Controller
{
}