<?php

namespace App\Http\Controllers;

use App\Models\TransaccionPendiente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TransaccionController extends Controller
{
/**
 * @OA\Post(
 *     path="/transactions",
 *     summary="Crear y propagar una transacción",
 *     tags={"Transacciones"},
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             required={"persona_id","institucion_id","titulo_obtenido","fecha_fin"},
 *             @OA\Property(property="persona_id", type="string", format="uuid"),
 *             @OA\Property(property="institucion_id", type="string", format="uuid"),
 *             @OA\Property(property="programa_id", type="string", format="uuid"),
 *             @OA\Property(property="titulo_obtenido", type="string", example="Ingeniero en Sistemas"),
 *             @OA\Property(property="fecha_fin", type="string", format="date", example="2024-06-01")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Transacción guardada y propagada"),
 *     @OA\Response(response=400, description="Campos requeridos faltantes")
 * )
 */    
public function store(Request $request)
    {
        $datos = $this->normalizar($request->all());

        $faltantes = [];
        foreach (['persona_id', 'institucion_id', 'titulo_obtenido', 'fecha_fin'] as $campo) {
            if (empty($datos[$campo])) $faltantes[] = $campo;
        }

        if (!empty($faltantes)) {
            return response()->json(['error' => 'Campos requeridos faltantes: ' . implode(', ', $faltantes)], 400);
        }

        TransaccionPendiente::create(['datos' => json_encode($datos)]);
        Log::info('[Transaccion] Guardada: ' . ($datos['titulo_obtenido'] ?? ''));

        if ($request->header('X-Propagated') !== 'true') {
            $this->propagar($datos);
        }

        return response()->json(['mensaje' => 'Transacción guardada y propagada'], 201);
    }

    public function receive(Request $request)
    {
        $datos = $this->normalizar($request->all());
        TransaccionPendiente::create(['datos' => json_encode($datos)]);
        Log::info('[Transaccion] Recibida de otro nodo: ' . ($datos['titulo_obtenido'] ?? ''));

        return response()->json(['mensaje' => 'Transacción recibida']);
    }

    private function normalizar(array $body): array
    {
        return array_filter([
            'persona_id'      => $body['persona_id']      ?? $body['personaId']      ?? null,
            'institucion_id'  => $body['institucion_id']  ?? $body['institucionId']  ?? null,
            'programa_id'     => $body['programa_id']     ?? $body['programaId']     ?? null,
            'titulo_obtenido' => $body['titulo_obtenido'] ?? $body['tituloObtenido'] ?? null,
            'fecha_fin'       => $body['fecha_fin']       ?? $body['fechaFin']       ?? null,
            'fecha_inicio'    => $body['fecha_inicio']    ?? $body['fechaInicio']    ?? null,
            'numero_cedula'   => $body['numero_cedula']   ?? $body['numeroCedula']   ?? null,
            'titulo_tesis'    => $body['titulo_tesis']    ?? $body['tituloTesis']    ?? null,
            'menciones'       => $body['menciones']       ?? null,
            'firmado_por'     => $body['firmado_por']     ?? $body['firmadoPor']     ?? null,
        ], fn($v) => $v !== null);
    }

    private function propagar(array $datos): void
{
    $nodos = DB::table('nodos')->get();

    // Snake_case puro — Express valida exactamente estos campos
    $payload = [
        'persona_id'      => $datos['persona_id']      ?? null,
        'institucion_id'  => $datos['institucion_id']  ?? null,
        'programa_id'     => $datos['programa_id']     ?? null,
        'titulo_obtenido' => $datos['titulo_obtenido'] ?? null,
        'fecha_fin'       => $datos['fecha_fin']        ?? null,
        'fecha_inicio'    => $datos['fecha_inicio']     ?? null,
        'numero_cedula'   => $datos['numero_cedula']    ?? null,
        'titulo_tesis'    => $datos['titulo_tesis']     ?? null,
        'menciones'       => $datos['menciones']        ?? null,
        'firmado_por'     => $datos['firmado_por']      ?? null,
        // camelCase también por si acaso
        'personaId'      => $datos['persona_id']      ?? null,
        'institucionId'  => $datos['institucion_id']  ?? null,
        'tituloObtenido' => $datos['titulo_obtenido'] ?? null,
        'fechaFin'       => $datos['fecha_fin']        ?? null,
    ];

    foreach ($nodos as $nodo) {
        // Express solo tiene /transactions — probar primero ese
        $endpoints = ['/transactions', '/transactions/receive', '/api/transactions/receive'];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['X-Propagated' => 'true'])
                    ->post("{$nodo->url}{$endpoint}", $payload);

                if ($response->successful()) {
                    Log::info("[Propagacion TX] OK → {$nodo->url}{$endpoint}");
                    break; // con uno que funcione pasamos al siguiente nodo
                }
            } catch (\Exception $e) {
                Log::warning("[Propagacion TX] Falló {$nodo->url}{$endpoint}: " . $e->getMessage());
            }
        }
    }
}
}