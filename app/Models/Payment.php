<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        "chat_id",
        "start_date",
        "end_date",
        "active",
    ];
}
