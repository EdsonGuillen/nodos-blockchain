<?php
namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\TransaccionPendiente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransaccionController extends Controller
{
    // POST /transactions
    public function store(Request $request)
    {
        $datos = $request->all();
        TransaccionPendiente::create(['datos' => json_encode($datos)]);

        // Propagar a los demás nodos
        $nodos = Nodo::all();
        foreach ($nodos as $nodo) {
            $propagado = false;

            // Intenta ruta Laravel
            try {
                $res = Http::timeout(5)->post("{$nodo->url}/api/transactions/receive", $datos);
                if ($res->successful()) {
                    Log::info("Transacción propagada (Laravel) a {$nodo->url}");
                    $propagado = true;
                }
            } catch (\Exception $e) {}

            // Si falló, intenta ruta Express/Next.js
            if (!$propagado) {
                try {
                    Http::timeout(5)->post("{$nodo->url}/transactions", $datos);
                    Log::info("Transacción propagada (Express) a {$nodo->url}");
                } catch (\Exception $e) {
                    Log::warning("No se pudo propagar a {$nodo->url}");
                }
            }
        }

        return response()->json(['mensaje' => 'Transacción guardada y propagada']);
    }

    // POST /transactions/receive  ← recibe de otros nodos (no re-propaga)
    public function receive(Request $request)
    {
        $datos = $request->all();
        TransaccionPendiente::create(['datos' => json_encode($datos)]);
        Log::info("Transacción recibida de otro nodo");
        return response()->json(['mensaje' => 'Transacción recibida']);
    }
}