<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaccionPendiente extends Model
{
    protected $table = 'transacciones_pendientes';

    protected $fillable = ['datos'];
}
