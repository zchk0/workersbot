<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotSession extends Model
{
     protected $fillable = [
        'user_id',
        'state',
        'data',
    ];
    
    protected $casts = [
        'data' => 'array',
    ];
}
