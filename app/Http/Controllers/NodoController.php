<?php

namespace App\Http\Controllers;

use App\Models\Grado;
use App\Models\TransaccionPendiente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NodoController extends Controller
{
    // ── POST /nodes/register ──────────────────────────────────────────────────
    public function register(Request $request)
    {
        $urls = $request->input('nodes') ?? [$request->input('url')];
        $urls = array_filter((array) $urls);

        if (empty($urls)) return response()->json(['error' => 'Se requiere url o nodes'], 400);

        $registrados = [];
        foreach ($urls as $url) {
            $url = rtrim($url, '/');
            if (!$url || $url === config('app.url')) continue;

            if (!DB::table('nodos')->where('url', $url)->exists()) {
                DB::table('nodos')->insert(['url' => $url, 'created_at' => now(), 'updated_at' => now()]);
                $registrados[] = $url;
            }
        }

        return response()->json([
            'mensaje'      => 'Nodos registrados',
            'registrados'  => $registrados,
            'nodosActivos' => DB::table('nodos')->pluck('url'),
        ]);
    }

    // ── GET /nodes ────────────────────────────────────────────────────────────
    public function listar()
    {
        return response()->json(DB::table('nodos')->pluck('url'));
    }

    // ── GET /nodes/resolve ────────────────────────────────────────────────────
    public function resolve()
    {
        $nodos         = DB::table('nodos')->get();
        $longitudLocal = DB::table('grados')->count();
        $mejorCadena   = null;

        foreach ($nodos as $nodo) {
            try {
                $response = Http::timeout(5)->get("{$nodo->url}/chain");
                if (!$response->successful()) $response = Http::timeout(5)->get("{$nodo->url}/api/chain");

                if ($response->successful()) {
                    $json = $response->json();
                    $cadenaRemota   = $json['chain'] ?? [];
                    $longitudRemota = $json['longitud'] ?? $json['length'] ?? count($cadenaRemota);

                    if ($longitudRemota > $longitudLocal && $this->esValida($cadenaRemota)) {
                        $longitudLocal = $longitudRemota;
                        $mejorCadena   = $cadenaRemota;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Fallo al contactar nodo: {$nodo->url}");
            }
        }

        if ($mejorCadena) {
            foreach ($mejorCadena as $bloque) {
                $hashActual = $bloque['hash_actual'] ?? $bloque['hashActual'] ?? null;
                if (!$hashActual) continue;

                if (!DB::table('grados')->where('hash_actual', $hashActual)->exists()) {
                    $datos = [
                        'persona_id'      => $bloque['persona_id'] ?? $bloque['personaId'] ?? null,
                        'institucion_id'  => $bloque['institucion_id'] ?? $bloque['institucionId'] ?? null,
                        'programa_id'     => $bloque['programa_id'] ?? $bloque['programaId'] ?? null,
                        'fecha_fin'       => $bloque['fecha_fin'] ?? $bloque['fechaFin'] ?? null,
                        'titulo_obtenido' => $bloque['titulo_obtenido'] ?? $bloque['tituloObtenido'] ?? null,
                        'hash_actual'     => $hashActual,
                        'hash_anterior'   => $bloque['hash_anterior'] ?? $bloque['hashAnterior'] ?? null,
                        'nonce'           => $bloque['nonce'] ?? 0,
                        'firmado_por'     => $bloque['firmado_por'] ?? $bloque['firmadoPor'] ?? null,
                    ];

                    DB::transaction(function () use ($datos) {
                        DB::statement('SET LOCAL session_replication_role = replica;');
                        DB::table('grados')->insert(array_filter($datos) + ['id' => (string) \Illuminate\Support\Str::uuid(), 'creado_en' => now()]);
                    });

                    // Limpiar la transacción pendiente si se sincronizó
                    if (!empty($datos['persona_id'])) {
                        TransaccionPendiente::all()->each(function ($p) use ($datos) {
                            $d = json_decode($p->datos, true);
                            if (($d['persona_id'] ?? $d['personaId'] ?? '') == $datos['persona_id']) $p->delete();
                        });
                    }
                }
            }
            return response()->json(['mensaje' => 'Cadena reemplazada', 'reemplazada' => true, 'longitud' => $longitudLocal]);
        }

        return response()->json(['mensaje' => 'Cadena local es la más larga', 'reemplazada' => false, 'longitud' => $longitudLocal]);
    }

    // ── Validar cadena entrante ───────────────────────────────────────────────
    private function esValida(array $cadena): bool
    {
        for ($i = 1; $i < count($cadena); $i++) {
            $hashActualAnterior = $cadena[$i - 1]['hash_actual'] ?? $cadena[$i - 1]['hashActual'] ?? null;
            $hashAnteriorBloque = $cadena[$i]['hash_anterior'] ?? $cadena[$i]['hashAnterior'] ?? null;
            $hashActualBloque   = $cadena[$i]['hash_actual'] ?? $cadena[$i]['hashActual'] ?? null;

            if ($hashAnteriorBloque !== $hashActualAnterior || !Grado::esHashValido($hashActualBloque)) {
                return false;
            }
        }
        return true;
    }
}