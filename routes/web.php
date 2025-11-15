<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::get('questions', [QuestionController::class, 'questions'])->name('questions');
Route::get("/", [QuestionController::class, "showTestForm"]);
Route::post('/save-rating', [QuestionController::class, 'makeUserRating']);
Route::get('/top-ratings', [QuestionController::class,'topRatings']);
