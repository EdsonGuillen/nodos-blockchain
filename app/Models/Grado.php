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
     * Genera el hash SHA256 con la fórmula que coincide EXACTAMENTE con Express:
     *
     *   `${persona_id}${institucion_id}${titulo_obtenido}${fecha_fin}${hash_anterior}${nonce}`
     *
     * CORRECCIÓN CLAVE: cuando hash_anterior es null, JavaScript template literals
     * producen el string "null", no string vacío.
     * PHP debe hacer lo mismo: usar "null" (no '').
     */
    public static function generarHash(
        string $personaId,
        string $institucionId,
        string $titulo,
        string $fechaFin,
        ?string $hashAnterior,
        int $nonce
    ): string {
        // JS:  ${null}  →  "null"   (string literal "null")
        // PHP: ?? ''    →  ""       (string vacío) ← esto causaba el mismatch
        // FIX: ?? 'null' →  "null"  (igual que JS)
        $hashPrevio = $hashAnterior ?? 'null';

        $data = $personaId . $institucionId . $titulo . $fechaFin . $hashPrevio . $nonce;
        return hash('sha256', $data);
    }

    /**
     * Verifica que el hash cumple la dificultad de Proof of Work.
     */
    public static function esHashValido(string $hash, int $dificultad = 3): bool
    {
        return str_starts_with($hash, str_repeat('0', $dificultad));
    }

    /**
     * Ejecuta el algoritmo Proof of Work.
     * Usa generarHash() internamente para garantizar consistencia con Express.
     *
     * @return array{ hash: string, nonce: int }
     */
    public static function minar($personaId, $institucionId, $tituloObtenido, $fechaFin, $hashAnterior = null)
    {
        $nonce = 0;

        do {
            $nonce++;
            $hash = self::generarHash(
                (string) $personaId,
                (string) $institucionId,
                (string) $tituloObtenido,
                (string) $fechaFin,
                $hashAnterior,
                $nonce
            );
        } while (!str_starts_with($hash, '000'));

        return ['hash' => $hash, 'nonce' => $nonce];
    }
}