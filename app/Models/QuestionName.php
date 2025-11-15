<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionName extends Model
{
    protected $fillable = [
        "question_name",
        "chat_id",
        "active",
        "free"
    ];
}
