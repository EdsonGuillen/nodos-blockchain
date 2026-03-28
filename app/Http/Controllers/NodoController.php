<?php
namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Grado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NodoController extends Controller
{
    public function register(Request $request) {
    $url = $request->input('url');
    if (!$url) {
        return response()->json(['error' => 'URL requerida'], 400);
    }

    $existe = DB::table('nodos')->where('url', $url)->exists();
    if ($existe) {
        return response()->json(['mensaje' => 'El nodo ya existe'], 200);
    }

    DB::table('nodos')->insert([
        'url' => $url,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['mensaje' => 'Nodo registrado correctamente'], 201);
}

    // GET /nodes/resolve  ← algoritmo de consenso
   public function resolve()
{
    $nodos = Nodo::all();
    $cadenaLocal = Grado::orderBy('creado_en')->get();
    $longitudLocal = $cadenaLocal->count();
    $mejorCadena = null;

    foreach ($nodos as $nodo) {
        try {
            // Intenta ruta Laravel primero
            $response = Http::timeout(5)->get("{$nodo->url}/api/chain");
            if (!$response->successful()) {
                // Intenta ruta Express
                $response = Http::timeout(5)->get("{$nodo->url}/chain");
            }

            if ($response->successful()) {
                $json = $response->json();

                // Soporta formato Laravel (chain/longitud) y Express (chain/length)
                $cadenaRemota = $json['chain'] ?? [];
                $longitudRemota = $json['longitud'] ?? $json['length'] ?? count($cadenaRemota);

                Log::info("Nodo {$nodo->url} tiene longitud: $longitudRemota");

                if ($longitudRemota > $longitudLocal && $this->esValida($cadenaRemota)) {
                    $longitudLocal = $longitudRemota;
                    $mejorCadena = $cadenaRemota;
                }
            }
        } catch (\Exception $e) {
            Log::warning("No se pudo contactar nodo {$nodo->url}: " . $e->getMessage());
        }
    }

    if ($mejorCadena && $longitudLocal > $cadenaLocal->count()) {
    Grado::truncate();
        foreach ($mejorCadena as $bloque) {
            Grado::create($bloque);
        }
        Log::info("Cadena reemplazada. Nueva longitud: $longitudLocal");
        return response()->json(['mensaje' => 'Cadena reemplazada', 'longitud' => $longitudLocal]);
    }

    return response()->json(['mensaje' => 'Cadena local ya es la más larga', 'longitud' => $cadenaLocal->count()]);
}

    private function esValida(array $cadena): bool
    {
        for ($i = 1; $i < count($cadena); $i++) {
            $bloque = $cadena[$i];
            $anterior = $cadena[$i - 1];

            if ($bloque['hash_anterior'] !== $anterior['hash_actual']) return false;

            $hashCalculado = Grado::generarHash(
                $bloque['persona_id'],
                $bloque['institucion_id'],
                $bloque['titulo_obtenido'],
                $bloque['fecha_fin'],
                $bloque['hash_anterior'],
                $bloque['nonce']
            );

            if ($hashCalculado !== $bloque['hash_actual']) return false;
            if (!Grado::esHashValido($bloque['hash_actual'])) return false;
        }
        return true;
    }
}