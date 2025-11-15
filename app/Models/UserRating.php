<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRating extends Model
{
    protected $fillable = [
        "chat_id",
        "correct_answer",
        "incorrect_answer",
        "total_answer",
    ];
}
