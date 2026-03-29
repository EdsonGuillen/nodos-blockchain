<?php

namespace App\Http\Controllers;
use OpenApi\Attributes as OA;

use App\Models\Grado;
use App\Models\TransaccionPendiente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BlockchainController extends Controller
{
    // ── Insertar bloque saltando FK ───────────────────────────────────────────
    private function asegurarGenesis(): void
    {
        if (DB::table('grados')->count() > 0) return;

        $genesis = [
            'persona_id'      => '00000000-0000-0000-0000-000000000000',
            'institucion_id'  => '00000000-0000-0000-0000-000000000000',
            'programa_id'     => '00000000-0000-0000-0000-000000000000',
            'titulo_obtenido' => 'Bloque Genesis - Red Blockchain',
            'fecha_fin'       => '2024-01-01',
            'hash_anterior'   => str_repeat('0', 64),
            'firmado_por'     => config('app.url'),
        ];

        $resultado = Grado::minar(
            $genesis['persona_id'],
            $genesis['institucion_id'],
            $genesis['titulo_obtenido'],
            $genesis['fecha_fin'],
            $genesis['hash_anterior']
        );

        $this->insertarBloque(array_merge($genesis, [
            'hash_actual'   => $resultado['hash'],
            'hash_anterior' => $genesis['hash_anterior'],
            'nonce'         => $resultado['nonce'],
        ]));

        Log::info('[Genesis] Bloque génesis creado: ' . $resultado['hash']);
    }

    private function insertarBloque(array $datos): Grado
    {
        $id = (string) Str::uuid();
        DB::transaction(function () use ($datos, $id) {
            DB::statement('SET LOCAL session_replication_role = replica;');
            DB::table('grados')->insert(array_merge($datos, [
                'id'        => $id,
                'creado_en' => now(),
            ]));
        });
        return Grado::find($id);
    }

    // ── GET /chain ────────────────────────────────────────────────────────────
    #[OA\Get(path: '/chain', summary: 'Obtener cadena completa', tags: ['Blockchain'],
        responses: [new OA\Response(response: 200, description: 'Cadena de bloques')]
    )]
    public function chain()
    {
        $cadena = Grado::orderBy('creado_en')->get();
        return response()->json([
            'node_id'  => config('app.url'),
            'chain'    => $cadena,
            'longitud' => $cadena->count(),
            'length'   => $cadena->count(),
        ]);
    }

    // ── POST /mine ────────────────────────────────────────────────────────────
    #[OA\Post(path: '/mine', summary: 'Minar bloque pendiente', tags: ['Blockchain'],
        responses: [
            new OA\Response(response: 200, description: 'Bloque minado'),
            new OA\Response(response: 400, description: 'Sin transacciones pendientes')
        ]
    )]
    public function mine()
    {
        $this->asegurarGenesis();

        $pendientes = TransaccionPendiente::all();
        if ($pendientes->isEmpty()) {
            return response()->json(['error' => 'No hay transacciones pendientes'], 400);
        }

        // ── Sincronizar con peers antes de minar ──────────────────────────
        $nodos = DB::table('nodos')->get();
        foreach ($nodos as $nodo) {
            try {
                $response = Http::timeout(5)->get("{$nodo->url}/chain");
                if (!$response->successful()) {
                    $response = Http::timeout(5)->get("{$nodo->url}/api/chain");
                }
                if ($response->successful()) {
                    $json           = $response->json();
                    $cadenaRemota   = $json['chain'] ?? [];
                    $longitudRemota = count($cadenaRemota);
                    $longitudLocal  = DB::table('grados')->count();

                    if ($longitudRemota > $longitudLocal) {
                        Log::info("[Mine] Sincronizando con {$nodo->url} antes de minar ({$longitudRemota} vs {$longitudLocal})");
                        foreach ($cadenaRemota as $bloque) {
                            $hashActual = $bloque['hash_actual'] ?? $bloque['hashActual'] ?? null;
                            if (!$hashActual) continue;
                            $existe = DB::table('grados')->where('hash_actual', $hashActual)->exists();
                            if (!$existe) {
                                $campos = [
                                    'persona_id', 'institucion_id', 'programa_id',
                                    'fecha_inicio', 'fecha_fin', 'titulo_obtenido',
                                    'numero_cedula', 'titulo_tesis', 'menciones',
                                    'hash_actual', 'hash_anterior', 'nonce', 'firmado_por',
                                ];
                                $datos = array_filter(
                                    array_intersect_key($bloque, array_flip($campos)),
                                    fn($v) => $v !== null
                                );
                                if (!empty($datos['hash_actual'])) {
                                    try {
                                        DB::transaction(function () use ($datos) {
                                            DB::statement('SET LOCAL session_replication_role = replica;');
                                            DB::table('grados')->insert(array_merge($datos, [
                                                'id'        => (string) \Illuminate\Support\Str::uuid(),
                                                'creado_en' => now(),
                                            ]));
                                        });
                                    } catch (\Exception $e) {
                                        Log::warning("[Mine] No se pudo insertar bloque {$hashActual}: " . $e->getMessage());
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[Mine] No se pudo sincronizar con {$nodo->url}");
            }
        }

        // ── Minar ─────────────────────────────────────────────────────────
        $ultimoBloque = Grado::orderBy('creado_en', 'desc')->first();
        $hashAnterior = $ultimoBloque ? $ultimoBloque->hash_actual : null;
        $bloquesMinados = [];

        foreach ($pendientes as $pendiente) {
            $transaccion = json_decode($pendiente->datos, true);

            $personaId      = $transaccion['persona_id']      ?? $transaccion['personaId']      ?? '';
            $institucionId  = $transaccion['institucion_id']  ?? $transaccion['institucionId']  ?? '';
            $tituloObtenido = $transaccion['titulo_obtenido'] ?? $transaccion['tituloObtenido'] ?? '';
            $fechaFin       = $transaccion['fecha_fin']       ?? $transaccion['fechaFin']       ?? '';

            $resultado = Grado::minar($personaId, $institucionId, $tituloObtenido, $fechaFin, $hashAnterior);

            $datosNuevos = [
                'persona_id'      => $personaId,
                'institucion_id'  => $institucionId,
                'titulo_obtenido' => $tituloObtenido,
                'fecha_fin'       => $fechaFin,
                'hash_actual'     => $resultado['hash'],
                'hash_anterior'   => $hashAnterior,
                'nonce'           => $resultado['nonce'],
                'firmado_por'     => config('app.url'),
            ];

            $bloque = $this->insertarBloque(array_merge($transaccion, $datosNuevos));
            $pendiente->delete();

            $hashAnterior     = $bloque->hash_actual;
            $bloquesMinados[] = $bloque;

            Log::info("Bloque minado: {$bloque->hash_actual} | nonce: {$bloque->nonce}");
        }

        $ultimoBloque = end($bloquesMinados);

        // Propagar a otros nodos
        foreach ($nodos as $nodo) {
            $this->propagarBloque($nodo->url, $ultimoBloque);
        }

        return response()->json([
            'mensaje' => 'Bloque(s) minado(s)',
            'bloque'  => $ultimoBloque,
            'total'   => count($bloquesMinados),
        ]);
    }

    // ── POST /blocks/receive ──────────────────────────────────────────────────
    #[OA\Post(path: '/blocks/receive', summary: 'Recibir bloque de otro nodo', tags: ['Blockchain'],
        responses: [new OA\Response(response: 200, description: 'Bloque aceptado')]
    )]
    public function receiveBlock(Request $request)
    {
        $datos = $request->all();

        $hashActual = $datos['hashActual'] ?? $datos['hash_actual'] ?? null;
        if (!$hashActual) {
            return response()->json(['error' => 'Bloque inválido: falta hash_actual'], 400);
        }

        // Evitar duplicados
        if (DB::table('grados')->where('hash_actual', $hashActual)->exists()) {
            return response()->json(['mensaje' => 'Bloque ya existe'], 200);
        }

        $txArray = $datos['data']['transacciones'] ?? $datos['data']['transactions'] ?? null;

        if (is_array($txArray) && isset($txArray[0])) {
            $tx = $txArray[0];
        } elseif (is_array($txArray) && !empty($txArray)) {
            $tx = $txArray;
        } else {
            $tx = $datos;
        }

        $datosLimpios = [
            'persona_id'      => $tx['persona_id']      ?? $tx['personaId']      ?? $datos['persona_id']      ?? null,
            'institucion_id'  => $tx['institucion_id']  ?? $tx['institucionId']  ?? $datos['institucion_id']  ?? null,
            'programa_id'     => $tx['programa_id']     ?? $tx['programaId']     ?? $datos['programa_id']     ?? null,
            'titulo_obtenido' => $tx['titulo_obtenido'] ?? $tx['tituloObtenido'] ?? $datos['titulo_obtenido'] ?? null,
            'fecha_fin'       => $tx['fecha_fin']       ?? $tx['fechaFin']       ?? $datos['fecha_fin']       ?? null,
            'hash_actual'     => $hashActual,
            'hash_anterior'   => $datos['hash_anterior'] ?? $datos['hashAnterior'] ?? null,
            'nonce'           => $datos['nonce'] ?? 0,
            'firmado_por'     => $tx['firmado_por']     ?? $tx['firmadoPor']     ?? $datos['firmado_por']     ?? null,
        ];

        $this->insertarBloque(array_filter($datosLimpios));

        // Limpiar pendientes
        if (!empty($datosLimpios['persona_id'])) {
            TransaccionPendiente::all()->each(function ($pendiente) use ($datosLimpios) {
                $d = json_decode($pendiente->datos, true);

                $pid  = $d['persona_id']      ?? $d['personaId']      ?? '';
                $tit  = $d['titulo_obtenido'] ?? $d['tituloObtenido'] ?? '';
                $fech = $d['fecha_fin']        ?? $d['fechaFin']       ?? '';

                if (
                    $pid  === $datosLimpios['persona_id'] &&
                    $tit  === $datosLimpios['titulo_obtenido'] &&
                    $fech === $datosLimpios['fecha_fin']
                ) {
                    $pendiente->delete();
                    Log::info("Pendiente eliminada tras recibir bloque de peer: {$tit}");
                }
            });
        }

        Log::info("Bloque aceptado: {$hashActual}");
        return response()->json(['mensaje' => 'Bloque aceptado y guardado', 'hash' => $hashActual]);
    }

    // ── Propagar bloque a un nodo ─────────────────────────────────────────────
    private function propagarBloque(string $url, Grado $bloque): void
    {
        $hashAnterior = $bloque->hash_anterior;

        // ── CORRECCIÓN: serializar fecha_fin como string Y-m-d puro ──────────
        // El cast 'date:Y-m-d' convierte el campo a Carbon al leerlo del modelo.
        // Al propagar, Carbon puede serializar diferente que el string original
        // usado al calcular el hash. Forzamos formato explícito.
        $fechaFin    = $bloque->fecha_fin
            ? Carbon::parse($bloque->fecha_fin)->format('Y-m-d')
            : null;

        $fechaInicio = $bloque->fecha_inicio
            ? Carbon::parse($bloque->fecha_inicio)->format('Y-m-d')
            : null;

        $payload = [
            // snake_case
            'hash_actual'     => $bloque->hash_actual,
            'hash_anterior'   => $hashAnterior,
            'nonce'           => (int) $bloque->nonce,
            'persona_id'      => $bloque->persona_id,
            'institucion_id'  => $bloque->institucion_id,
            'programa_id'     => $bloque->programa_id,
            'titulo_obtenido' => $bloque->titulo_obtenido,
            'fecha_fin'       => $bloque->fecha_fin ? \Carbon\Carbon::parse($bloque->fecha_fin)->format('Y-m-d') : null,
            'fecha_inicio'    => $fechaInicio,    // ← string Y-m-d, no objeto Carbon
            'numero_cedula'   => $bloque->numero_cedula ?? null,
            'firmado_por'     => $bloque->firmado_por,
            // camelCase — Express busca ESTOS nombres
            'hashActual'      => $bloque->hash_actual,
            'hashAnterior'    => $hashAnterior,
            'personaId'       => $bloque->persona_id,
            'institucionId'   => $bloque->institucion_id,
            'programaId'      => $bloque->programa_id,
            'tituloObtenido'  => $bloque->titulo_obtenido,
            'fechaFin'        => $bloque->fechaFin ? \Carbon\Carbon::parse($bloque->fecha_fin)->format('Y-m-d') : null,
            'numeroCedula'    => $bloque->numero_cedula ?? null,
            'firmadoPor'      => $bloque->firmado_por,
        ];

        $endpoints = [
            '/blocks/receive',
            '/block',
            '/blocks',
            '/chain/receive',
            '/receive-block',
            '/receive',
            '/nodes/block',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(5)->post("{$url}{$endpoint}", $payload);
                if ($response->successful()) {
                    Log::info("[Propagacion] OK → {$url}{$endpoint}");
                    return;
                }
                Log::warning("[Propagacion] {$url}{$endpoint} respondió {$response->status()}: " . $response->body());
            } catch (\Exception $e) {
                Log::warning("[Propagacion] Falló {$url}{$endpoint}: " . $e->getMessage());
            }
        }

        Log::error("[Propagacion] No se pudo propagar bloque a ningún endpoint de {$url}");
    }

    // ── POST /nodes/register ──────────────────────────────────────────────────
    public function registerNodes(Request $request)
    {
        $urls = $request->input('nodos', []);

        if (empty($urls)) {
            return response()->json(['error' => 'No se enviaron nodos'], 400);
        }

        $registrados = [];
        foreach ($urls as $url) {
            if ($url !== config('app.url') && !\App\Models\Nodo::where('url', $url)->exists()) {
                \App\Models\Nodo::create(['url' => $url]);
                $registrados[] = $url;
            }
        }

        return response()->json([
            'mensaje'       => 'Nodos registrados exitosamente',
            'total_nuevos'  => count($registrados),
            'nodos_totales' => \App\Models\Nodo::pluck('url'),
        ]);
    }

    // ── GET /health ───────────────────────────────────────────────────────────
    public function health()
    {
        $pendientes = TransaccionPendiente::count();
        $bloques    = Grado::count();
        $nodos      = DB::table('nodos')->pluck('url');

        return response()->json([
            'status'     => 'ok',
            'nodeId'     => config('app.url'),
            'node_id'    => config('app.url'),
            'bloques'    => $bloques,
            'pendientes' => $pendientes,
            'peers'      => $nodos,
            'nodes'      => $nodos,
        ]);
    }
}