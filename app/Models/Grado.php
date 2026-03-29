<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Grado extends Model
{
    protected $table      = 'grados';
    protected $primaryKey = 'id';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = [
        'id',
        'persona_id',
        'institucion_id',
        'programa_id',
        'fecha_inicio',
        'fecha_fin',
        'titulo_obtenido',
        'numero_cedula',
        'titulo_tesis',
        'menciones',
        'hash_actual',
        'hash_anterior',
        'nonce',
        'firmado_por',
    ];

    protected $casts = [
    'fecha_fin'    => 'date:Y-m-d',
    'fecha_inicio' => 'date:Y-m-d',
];
    // Genera UUID automáticamente al crear
    protected static function booted(): void
    {
        static::creating(function (Grado $grado) {
            if (empty($grado->id)) {
                $grado->id = (string) Str::uuid();
            }
        });
    }

    // ── Métodos blockchain ────────────────────────────────────────────────────

    /**
     * Genera el hash SHA256 de un bloque usando la fórmula acordada en el examen:
     * SHA256(persona_id + institucion_id + titulo_obtenido + fecha_fin + hash_anterior + nonce)
     */
    
public static function generarHash(
    string $personaId,
    string $institucionId,
    string $titulo,
    string $fechaFin,
    ?string $hashAnterior,
    int $nonce
): string {
    // "null" como string igual que Express
    $hashPrevio = $hashAnterior ?? 'null';
    $data = $personaId . $institucionId . $titulo . $fechaFin . $hashPrevio . $nonce;
    return hash('sha256', $data);
}
    /**
     * Verifica que el hash cumpla la dificultad de Proof of Work.
     * La dificultad por defecto es 3 (hash empieza con "000").
     */
    public static function esHashValido(string $hash, int $dificultad = 3): bool
    {
        return str_starts_with($hash, str_repeat('0', $dificultad));
    }

    /**
     * Ejecuta el algoritmo Proof of Work:
     * incrementa el nonce hasta encontrar un hash que cumpla la dificultad.
     *
     * @return array{ hash: string, nonce: int }
     */
    public static function minar($personaId, $institucionId, $tituloObtenido, $fechaFin, $hashAnterior = null)
{
    $nonce = 0;
    // Si $hashAnterior es null, usa un string vacío para la concatenación
    $datosBase = $personaId . $institucionId . $tituloObtenido . $fechaFin . ($hashAnterior ?? '');

    do {
        $nonce++;
        $hash = hash('sha256', $datosBase . $nonce);
    } while (!str_starts_with($hash, '000')); // Los 3 ceros de la rúbrica

    return ['hash' => $hash, 'nonce' => $nonce];
}
}
