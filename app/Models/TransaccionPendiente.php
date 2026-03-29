<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaccionPendiente extends Model
{
    protected $table    = 'transacciones_pendientes';
    protected $fillable = ['datos'];

    // Guardar y leer como string JSON (los controladores hacen json_encode/decode manualmente)
    // Si quieres array automático cambia a 'array', pero entonces quita los json_encode/decode
    protected $casts = [
        'datos' => 'string',
    ];
}