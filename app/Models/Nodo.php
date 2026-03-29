<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nodo extends Model
{
    protected $table    = 'nodos';
    protected $fillable = ['url'];
}
