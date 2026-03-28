<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Grado extends Model
{
    protected $table = 'grados';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'persona_id', 'institucion_id', 'programa_id',
        'fecha_inicio', 'fecha_fin', 'titulo_obtenido',
        'numero_cedula', 'titulo_tesis', 'menciones',
        'hash_actual', 'hash_anterior', 'nonce', 'firmado_por',
    ];

    protected static function booted()
    {
        static::creating(function ($grado) {
            if (empty($grado->id)) {
                $grado->id = (string) Str::uuid();
            }
        });
    }

    // Genera el hash del bloque
    public static function generarHash(string $personaId, string $institucionId, string $titulo, string $fechaFin, string $hashAnterior, int $nonce): string
    {
        $data = $personaId . $institucionId . $titulo . $fechaFin . $hashAnterior . $nonce;
        return hash('sha256', $data);
    }

    // Valida que el hash cumpla la dificultad (empieza con "000")
    public static function esHashValido(string $hash, int $dificultad = 3): bool
    {
        return str_starts_with($hash, str_repeat('0', $dificultad));
    }

    // Proof of Work: encuentra el nonce correcto
    public static function minar(string $personaId, string $institucionId, string $titulo, string $fechaFin, string $hashAnterior, int $dificultad = 3): array
    {
        $nonce = 0;
        do {
            $hash = self::generarHash($personaId, $institucionId, $titulo, $fechaFin, $hashAnterior, $nonce);
            $nonce++;
        } while (!self::esHashValido($hash, $dificultad));

        return ['hash' => $hash, 'nonce' => $nonce - 1];
    }
}