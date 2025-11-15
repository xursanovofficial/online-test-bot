<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'question_name_id',
        'title',
        'a_variant',
        'b_variant',
        'c_variant',
        'd_variant',
        'correct_answer',
        'key',
        'test_number',
        'active'
    ];
}
