<?php

namespace App\Http\Telegram;

use App\Models\Payment;
use App\Models\Question;
use App\Models\QuestionName;
use App\Models\User;
use Carbon\Carbon;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Stringable;

class TelegramHandler extends WebhookHandler
{

    public function start()
    {
        if ($this->message) {
            $firstName = $this->message->from()->firstName();
            $username = $this->message->from()->username();
            $lastName = $this->message->from()->lastName();
            $chatId = $this->message->from()->id();
            $user = $this->createUser($chatId, $firstName, $username);
        }

        $url = env('APP_URL');
        $admin = $user->admin ? true : false;
        $storagePath = "public/documents/manual.mp4";
        $localPath = Storage::disk('public')->path($storagePath);

        Telegraph::chat($chatId)->html("Assalamu alaykum <b>$firstName\nOnline Test</b> botimizga xush kelibsiz!")
            ->replyKeyboard(
                ReplyKeyboard::make()
                    ->button("Bosh sahifa 🏠")
                    ->button("Qo'llanma ⭐️")
                    ->button("To'lov 💳")
                    ->button("Admin bilan aloqa 📞")
                    ->button("Test yaratish 📕")
                    ->button('Test ishlash 📄')->webApp($url . "?chat_id=" . $chatId)
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq berish 🔐"))
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq olish 🔒"))
                    ->chunk(2)
                    ->inputPlaceholder("Assalamu alaykum...")
                    ->resize()
            )->send();

            if (Storage::disk('public')->exists($storagePath)) {
            $freeQuestionCount = env('FREE_QUESTION_COUNT');
            $message = "Botdan foydalanish uchun qo'llanma☝🏻 \n
🪄 Test yaratish bo'limida $freeQuestionCount martalik bepul test yarating\n
🛎 <strong>Test ishlash</strong> bo'limida testlarni bajarib ko'ring va agar ma'qul kelsa botga to'lov qilib botdan to'liq foydalanish huquqini oling.\n\n";
            Telegraph::chat($chatId)
                ->video($localPath)
                ->message($message)
                ->send();
            return;
        }
    }


    public function handleChatMessage(Stringable $text): void
    {
        if (!$this->message && !$this->callbackQuery) {
            $this->chat->message('Xatolik yuz berdi!')->send();
            return;
        }

        $firstName = $this->message->from()->firstName();
        $username = $this->message->from()->username();
        $chatId = $this->message->from()->id();

        $user = $this->createUser($chatId, $firstName, $username);

        switch ($text) {
            case "Bosh sahifa 🏠":
                $this->updateUserPage($chatId, User::HOME_PAGE);
                Telegraph::chat($chatId)->html("Siz <b>Bosh sahifa</b>dasiz!")->send();
                return;
                break;
        }

        switch ($user->page) {
            case User::PREPARING_TEST:
                $this->makeTest($chatId, $text);
                break;
            case User::ENTER_TEST_NAME:
                if (!$this->check($text)) {
                    Telegraph::chat($chatId)->message("Siz <b>Bosh sahifa</b>da emassiz!\nIltimos, Test uchun nom kiriting!")->send();
                    return;
                }
                $this->verifyTestName($chatId, $text);
                break;
            case User::ADD_RULE:
                $this->manageRule($chatId, $text, true);
                break;
            case User::REMOVE_RULE:
                $this->manageRule($chatId, $text, false);
                break;
            case User::HOME_PAGE:
                switch ($text) {
                    case "To'lov 💳":
                        $this->sendInfo($chatId);
                        break;
                    case "Qo'llanma ⭐️":
                        $this->manual($chatId);
                        break;
                    case "Admin bilan aloqa 📞":
                        $this->contactAdmin($chatId);
                        break;
                    case "Test yaratish 📕":
                        $this->enterTestName($chatId);
                        break;
                    case "Huquq berish 🔐":
                        $this->addRule($chatId, true);
                        break;
                    case "Huquq olish 🔒":
                        $this->addRule($chatId, false);
                        break;
                }
                break;
        }
    }

    private function manual($chatId)
    {
        $storagePath = "public/documents/manual.mp4";
        $localPath = Storage::disk('public')->path($storagePath);

        $freeQuestionCount = env('FREE_QUESTION_COUNT');
        $message = "Botdan foydalanish uchun qo'llanma: \n
🪄 Test yaratish bo'limida $freeQuestionCount martalik bepul test yarating\n
🛎 <strong>Test ishlash</strong> bo'limida testlarni bajarib ko'ring va agar ma'qul kelsa botga to'lov qilib botdan to'liq foydalanish huquqini oling.\n\n
1. O'zingiz uchun test yarating. \n
2. <strong>Test yaratish</strong> bo'limida ko'rsatilgan sun'iy intelekt yordamida formatlab oling \n
3. Formatlangan savollar to'plamini bizga yuboring \n
4. Biz siz yuborgan testlarni siz uchun <strong>Test ishlash</strong> bo'limida onlayn test ko'rinishida taqdim etamiz";
        if (!Storage::disk('public')->exists($storagePath)) {
            Telegraph::chat($chatId)
                ->html($message)
                ->send();
            return;
        }

        Telegraph::chat($chatId)->html($message)->video($localPath)->send();
    }

    public function check($text)
    {
        if ($text == "To'lov 💳" || $text == "Qo'llanma ⭐️" || $text == "Admin bilan aloqa 📞" || $text == "Test yaratish 📕" || $text == "Huquq berish 🔐" || $text == "Huquq olish 🔒") {
            return false;
        }
        return true;
    }

    private function sendInfo($chatId)
    {
        $username = "https://t.me/" . env("USERNAME_TELEGRAM");
        $paymentSum = env('PAYMENT_SUM');
        // $message = "<i>kursiv</i>\n<em>kursiv</em>\n<u>tagiga chizilgan</u>\n<s>ustidan chizilgan</s>\n<del>ustidan chizilgan</del>\n<tg-spoiler>$paymentSum</tg-spoiler>\n<pre>block code</pre>\n";
        $message = "<code>$chatId</code> ushbu ID raqamingizni va to'lov qilinganlik haqida chekni $username profilga yuboring!\nOylik to'lov summasi: <tg-spoiler>$paymentSum</tg-spoiler>";

        Telegraph::chat($chatId)->html($message)->send();
    }

    private function addRule($chatId, $addRule)
    {
        Telegraph::chat($chatId)->message("Iltimos, foydalanuvchi chat_id raqamini kiriting:")->send();
        $page = $addRule ? User::ADD_RULE : User::REMOVE_RULE;
        $this->updateUserPage($chatId, $page);
    }

    private function contactAdmin($chatId)
    {
        $envUsername = "https://t.me/" . env("USERNAME_TELEGRAM");
        $message = "Savol va takliflar uchun $envUsername profilga murojaat qiling!";
        Telegraph::chat($chatId)->html($message)->send();
    }

    private function manageRule($chatId, $userChatId, $addRule)
    {
        $user = User::where('chat_id', $userChatId)->first();
        $admin = User::where('chat_id', $chatId)->where('admin', true)->first();
        if (!$admin) {
            Telegraph::chat($chatId)->message("Sizda bunday huquq yo'q!")->send();
            $this->updateUserPage($chatId, User::HOME_PAGE);
        }
        if (!$user) {
            Telegraph::chat($chatId)->message("Iltimos, botga start bosgan userning chat ID raqamini kiriting!")->send();
            return;
        }

        $keyboard = Keyboard::make()->row([
            Button::make('Ha ✅')->action('verifyRule')->param('verify', 'yes')->param('rule', $addRule)->param('userChatId', $userChatId),
            Button::make("Yo'q ❌")->action('verifyRule')->param('verify', 'no')->param('rule', $addRule)->param('userChatId', $userChatId)
        ]);
        $rule = $addRule ? "ga huquq berishni" : "dan huquq olishni";
        $message = "<b>$user->full_name</b> nomli foydalanuvchi$rule tasdiqlaysizmi?";
        Telegraph::chat($chatId)->html($message)->keyboard($keyboard)->send();
    }

    public function verifyRule($verify, $userChatId, $rule)
    {
        $callbackQuery = request()->input('callback_query');
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $chatId = $callbackQuery['from']['id'] ?? null;
        if ($verify == 'yes') {

            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();
            $this->giveRule($userChatId, $rule, $chatId);
        } elseif ($verify == 'no') {
            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();
            $this->addRule($chatId, $rule);
        } else {
            Telegraph::chat($chatId)->message("Iltimos, Ha ✅ yoki Yo'q ❌ belsini tanlang!")->send();
        }
    }

    public function giveRule($userChatId, $rule, $chatId)
    {

        if ($rule) {
            $payment = Payment::where("chat_id", $userChatId)->orderBy('id', 'desc')->first();
            if ($payment) {
                $payment->update([
                    "active" => false,
                    "end_date" => date("Y-m-d")
                ]);
            }
            Payment::create([
                "chat_id" => $userChatId,
                "start_date" => date("Y-m-d"),
                "end_date" => Carbon::now()->addMonth()->format('Y-m-d'),
                "active" => true
            ]);

            Telegraph::chat($chatId)->message("Huquq muvaffaqqiyatli berildi")->send();
        } else {
            $payment = Payment::where("chat_id", $userChatId)->where('active', true)->orderBy('id', 'desc')->first();
            if ($payment) {
                $payment->update([
                    "active" => false,
                    "end_date" => date("Y-m-d")
                ]);
            } else {
                Telegraph::chat($chatId)->message("Userning active to'lovi topilmadi")->send();
            }
            Telegraph::chat($chatId)->message("Huquq muvaffaqqiyatli olindi")->send();
        }
        $this->updateUserPage($chatId, User::HOME_PAGE);
    }

    private function enterTestName($chatId)
    {
        $message = "Iltimos, Test uchun nom kiriting!";
        Telegraph::chat($chatId)->message($message)->send();
        $this->updateUserPage($chatId, User::ENTER_TEST_NAME);
    }

    private function verifyTestName($chatId, $testName)
    {
        if (strpos($testName, ' ') !== false) {
            Telegraph::chat($chatId)
                ->message("Iltimos, test nomida bo'shliq ishlatmang!\nMasalan: test_nomi yoki test1 kabi")
                ->send();
            return;
        }
        $oldTestName = QuestionName::where('chat_id', $chatId)->where('active', true)->where('question_name', $testName)->first();
        if ($oldTestName) {
            Telegraph::chat($chatId)->html("Sizda <b>$testName</b> nomli test mavjud. Iltimos boshqa nom kiriting:")->send();
            return;
        }
        $keyboard = Keyboard::make()->row([
            Button::make('Ha ✅')->action('verify')->param('verify', 'yes')->param('chatId', $chatId)->param("testName", $testName),
            Button::make("Yo'q ❌")->action('verify')->param('verify', 'no')->param('chatId', $chatId)->param("testName", $testName)
        ]);
        $message = "Test nomi <b>$testName</b> ekanligini tasdiqlaysizmi?";
        Telegraph::chat($chatId)->html($message)->keyboard($keyboard)->send();
    }

    public function verify($verify, $chatId, $testName)
    {
        $callbackQuery = request()->input('callback_query');
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        if ($verify == 'yes') {
            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();
            $this->createTest($chatId, $testName);
        } elseif ($verify == 'no') {
            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();
            $this->enterTestName($chatId);
        } else {
            Telegraph::chat($chatId)->message("Iltimos, Ha ✅ yoki Yo'q ❌ belsini tanlang!")->send();
        }
    }

    private function createTest($chatId, $testName)
    {
        QuestionName::create([
            "chat_id" => $chatId,
            "question_name" => $testName,
            "active" => false,
            "free" => false
        ]);
        $AI = env("AI");
        // $message = "Iltimos, quyidagi struktura bo'yicha testlar yozilgan faylni yuboring!";
        $message = "Iltimos, $AI sun'iy intelekt saytiga kirib, testlar yozilgan faylingizni va pastdagi promptni sun'iy intelektga saytiga yuboring\n 
Bu testlar yozilgan faylni formatlab beradi\n
So'ngra bizga formatlangan testlar to'plamini yuboring";
        $structuraMessage = "<pre>Put a ? at the end of each question in this file.
For each question, if there are no options, write the fake options A), B), C) and D) among them, and make sure that the correct answer is included, and the remaining fake options are close in meaning to the correct answer.
Randomly place the correct answer options A), B), C) and D) among the options.
Please write the correct answer option below the options for each question in the form Javob: A</pre>";
        $example = "Savollar ushbu ko'rinishda bo'lishi kerak: \n\n <b>Apple so'zining ma'nosi nima? \n\n A) olma \n B) nok \n C) behi \n D) uzum \n\n Javob: A</b>";
        $warning = "Eslatib o'tamiz, savollar quyidagi tartibda bo'lmasa, savol va to'g'ri javoblar aralashib ketishi mumkin!";
        Telegraph::chat($chatId)->html($message)->send();
        Telegraph::chat($chatId)->html($structuraMessage)->send();
        Telegraph::chat($chatId)->html($example)->send();
        Telegraph::chat($chatId)->html($warning)->send();
        $this->updateUserPage($chatId, User::PREPARING_TEST);
    }

    private function makeTest($chatId, $text)
    {
        $questionName = QuestionName::where('chat_id', $chatId)->where('active', false)->orderBy('id', 'desc')->first();

        $text = preg_replace('/(?<!\n)([A-D]\))/', "\n$1", $text);
        $text = preg_replace('/(?<!\n)(Javob:)/', "\n$1", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{2,}/", "\n", $text);

        $fileContext = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "\n";

        $pattern = '/(.*?)\nA\)(.*?)\nB\)(.*?)\nC\)(.*?)\nD\)(.*?)\nJavob:\s*(.*?)(?:\n(?=\d+\.\s)|\s*$)/s';
        preg_match_all($pattern, $fileContext, $matches, PREG_SET_ORDER);

        $questions = [];
        $testNumber = 1;

        foreach ($matches as $match) {
            $question = preg_replace('/^\d+\.\s*/', '', trim($match[1]));
            $a = trim($match[2]);
            $b = trim($match[3]);
            $c = trim($match[4]);
            $d = trim($match[5]);
            $correct = strtolower(trim($match[6]));

            $questions[] = [
                'title' => $question,
                'a_variant' => $a,
                'b_variant' => $b,
                'c_variant' => $c,
                'd_variant' => $d,
                'correct_answer' => $correct,
                'key' => $questionName->question_name,
                'active' => true,
                'test_number' => $testNumber,
                'question_name_id' => $questionName->id
            ];

            $testNumber++;
        }

        if (empty($questions)) {
            Telegraph::chat($chatId)->message("Iltimos testlar to'plamini ko'rsatilgan struktura bo'yicha yuboring!")->send();
            return;
        }
        Question::insert($questions);
        $freeQuestionCount = QuestionName::where('chat_id', $chatId)->where('active', true)->where('free', true)->count();
        $questionName->update([
            'active' => true,
            'free' => $freeQuestionCount < env('FREE_QUESTION_COUNT') ? true : false
        ]);
        $this->updateUserPage($chatId, User::HOME_PAGE);
        Telegraph::chat($chatId)->message("Testlar muvaffaqqiyatli yaratildi!")->send();
        Telegraph::chat($chatId)->html("Yaratilgan testlarni <b>Test ishlash</b> bo'limida ko'rishingiz mumkin!")->send();
    }


    private function updateUserPage($chatId, $page)
    {
        $user = User::where('chat_id', $chatId)->first();
        $user->update([
            "page" => $page
        ]);
    }

    private function createUser($chatId, $firstName, $username)
    {
        $user = User::where('chat_id', $chatId)->where('active', true)->first();

        if (!$user) {
            $user = User::create([
                'full_name' => $firstName,
                'username' => $username ?? "",
                'chat_id' => $chatId
            ]);
        } else {
            $user->update([
                'full_name' => $firstName,
                'username' => $username ?? $user->username
            ]);
        }
        return $user;
    }
}
