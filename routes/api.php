<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get("getall", [QuestionController::class, "questions"]);
Route::post('upload/video', [QuestionController::class, 'uploadVideo']);