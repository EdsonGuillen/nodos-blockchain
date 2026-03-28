<?php
namespace App\Http\Controllers;

use App\Models\Grado;
use App\Models\Nodo;
use App\Models\TransaccionPendiente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlockchainController extends Controller
{
    // GET /chain
    public function chain()
    {
        $cadena = Grado::orderBy('creado_en')->get();
        return response()->json(['chain' => $cadena, 'longitud' => $cadena->count()]);
    }

    // POST /mine
    public function mine()
    {
        $pendientes = TransaccionPendiente::all();
        if ($pendientes->isEmpty()) {
            return response()->json(['error' => 'No hay transacciones pendientes'], 400);
        }

        $ultimoBloque = Grado::orderBy('creado_en', 'desc')->first();
        $hashAnterior = $ultimoBloque ? $ultimoBloque->hash_actual : str_repeat('0', 64);

        $transaccion = json_decode($pendientes->first()->datos, true);

        $resultado = Grado::minar(
            $transaccion['persona_id'],
            $transaccion['institucion_id'],
            $transaccion['titulo_obtenido'],
            $transaccion['fecha_fin'],
            $hashAnterior
        );

        $bloque = Grado::create(array_merge($transaccion, [
            'hash_actual'   => $resultado['hash'],
            'hash_anterior' => $hashAnterior,
            'nonce'         => $resultado['nonce'],
            'firmado_por'   => config('app.url'),
        ]));

        // Eliminar la transacción minada
        $pendientes->first()->delete();

        // Propagar el bloque a otros nodos
        $nodos = Nodo::all();
        foreach ($nodos as $nodo) {
            try {
                Http::timeout(5)->post("{$nodo->url}/blocks/receive", $bloque->toArray());
                Log::info("Bloque propagado a {$nodo->url}");
            } catch (\Exception $e) {
                Log::warning("No se pudo propagar bloque a {$nodo->url}");
            }
        }

        Log::info("Bloque minado: {$bloque->hash_actual} | nonce: {$bloque->nonce}");
        return response()->json(['mensaje' => 'Bloque minado', 'bloque' => $bloque]);
    }

    // POST /blocks/receive  ← recibe bloque de otro nodo y lo valida
    public function receiveBlock(\Illuminate\Http\Request $request)
{
    $datos = $request->all();

    // Si viene de Node.js, adaptamos el formato
    if (isset($datos['hashActual'])) {
        $tx = $datos['data']['transacciones'] ?? [];
        $datos = [
            'persona_id'      => $tx['personaId'] ?? null,
            'institucion_id'  => $tx['institucionId'] ?? null,
            'programa_id'     => $tx['programaId'] ?? null,
            'titulo_obtenido' => $tx['tituloObtenido'] ?? null,
            'fecha_fin'       => $tx['fechaFin'] ?? null,
            'firmado_por'     => $tx['firmadoPor'] ?? null,
            'hash_actual'     => $datos['hashActual'],
            'hash_anterior'   => $datos['hashAnterior'],
            'nonce'           => $datos['nonce']
        ];
    }

    $ultimoBloque = Grado::orderBy('creado_en', 'desc')->first();
    $hashEsperado = $ultimoBloque ? $ultimoBloque->hash_actual : str_repeat('0', 64);

    if ($datos['hash_anterior'] !== $hashEsperado) {
        return response()->json(['error' => 'hash_anterior inválido'], 400);
    }

    Grado::create($datos);
    return response()->json(['mensaje' => 'Bloque aceptado']);
}
}