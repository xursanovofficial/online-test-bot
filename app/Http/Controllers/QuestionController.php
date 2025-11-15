<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Question;
use App\Models\QuestionName;
use App\Models\User;
use App\Models\UserRating;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuestionController extends Controller
{

    public function showTestForm()
    {
        $chatId = request("chat_id");
        $questionNames = QuestionName::where('chat_id', $chatId)->where('active', true)->pluck('question_name', 'id');

        return view('home', compact('questionNames'));
    }

    public function questions()
    {
        $startNumber = request('start_number');
        $endNumber = request('end_number');
        $chatId = request('chat_id');
        $questionNameId = request('question_name_id');

        $user = User::where('chat_id', $chatId)
            ->first();

        if (!$user) {
            abort(404, 'User not found');
        }
        $freeTest = QuestionName::where('chat_id', $chatId)->where('id', $questionNameId)->where('free', true)->first();
        if (!$freeTest) {
            $payment = Payment::where('chat_id', $chatId)->where('active', true)->where('end_date', '>=', date('Y-m-d'))->first();
            if (!$payment) {
                return view("payment-day");
            }
        }


        $questions = Question::where('question_name_id', $questionNameId)
            ->where('test_number', '>=', $startNumber)
            ->where('test_number', '<=', $endNumber)
            ->get();
        if (empty($questions)) {
            return view("not-found");
        }

        $correctAnswers = $questions->pluck('correct_answer', 'id')->toArray();

        $questions = $questions->map(function ($question) {
            return [
                'id' => $question->id,
                'title' => $question->title,
                'a_variant' => $question->a_variant,
                'b_variant' => $question->b_variant,
                'c_variant' => $question->c_variant,
                'd_variant' => $question->d_variant,
                'active' => $question->active,
                'test_number' => $question->test_number
            ];
        });

        return view('questions', [
            'questions' => $questions,
            'correctAnswers' => $correctAnswers,
            'chatId' => $chatId
        ]);
    }

    public function makeUserRating(Request $request)
    {
        Log::info('salom');
        $userRating = UserRating::where("chat_id", $request->chat_id)->first();
        if (!$userRating) {
            UserRating::create([
                "chat_id" => $request->chat_id,
                "correct_answer" => $request->correct_answer,
                "incorrect_answer" => $request->incorrect_answer,
                "total_answer" => $request->total_answer
            ]);
        } else {
            $userRating->update([
                "correct_answer" => $userRating->correct_answer + $request->correct_answer,
                "incorrect_answer" => $userRating->incorrect_answer + $request->incorrect_answer,
                "total_answer" => $userRating->total_answer + $request->total_answer
            ]);
        }

        return response()->json([
            "status" => "success",
            "message" => "Rating muvaffaqiyatli saqlandi!",
        ]);
    }

    public function topRatings(Request $request)
    {
        $chatId = $request->query('chat_id');

        // Top 10 user
        $top10 = UserRating::select('user_ratings.*', 'users.full_name')
            ->join('users', 'users.chat_id', 'user_ratings.chat_id')
            ->orderByDesc('correct_answer')
            ->take(10)
            ->get();

        // O'z useri
        $userRating = null;
        if ($chatId) {
            $userRating = UserRating::select('user_ratings.*', 'users.full_name')
                ->join('users', 'users.chat_id', 'user_ratings.chat_id')
                ->where('user_ratings.chat_id', $chatId)
                ->first();
        }

        return response()->json([
            'top' => $top10,
            'user' => $userRating
        ]);
    }

    public function uploadVideo(Request $request)
    {
        // 1. File borligini tekshiramiz
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'Fayl yuklanmadi!'], 400);
        }

        $file = $request->file('file');

        // 2. Fayl mp4 ekanligini tekshiramiz
        if ($file->getClientOriginalExtension() !== 'mp4') {
            return response()->json(['error' => 'Faqat mp4 fayllar qabul qilinadi!'], 400);
        }

        // 3. Saqlash
        $path = $file->storeAs('public/documents', 'manual.mp4', 'public');

        return response()->json(['success' => 'Video yuklandi', 'path' => $path]);
    }
}
