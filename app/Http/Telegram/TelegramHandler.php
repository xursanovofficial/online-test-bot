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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stringable;

class TelegramHandler extends WebhookHandler
{

    public function start()
    {
        if ($this->message) {
            $firstName = $this->message->from()->firstName();
            $username = $this->message->from()->username();
            $chatId = $this->message->from()->id();
            $user = $this->createUser($chatId, $firstName, $username);
        }

        $url = env('APP_URL');
        $admin = $user->admin ? true : false;

        Telegraph::chat($chatId)->html("Assalamu alaykum <b>$firstName</b> xush kelibsiz!")
            ->replyKeyboard(
                ReplyKeyboard::make()
                    ->button("Bosh sahifa 🏠")
                    ->button("Stiker yaratish 🪄")
                    ->button("Test yaratish 📕")
                    ->button('Test ishlash 📄')->webApp($url . "?chat_id=" . $chatId)
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq berish 🔐"))
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq olish 🔒"))
                    ->chunk(2)
                    ->inputPlaceholder("Assalamu alaykum...")
                    ->resize()
            )->send();
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
                Telegraph::chat($chatId)->html("<b>Siz Bosh sahifadasiz!</b>")->send();
                return;
                break;
        }
        if (!$this->checkUserPage($chatId, $text)) {
            return;
        }

        switch ($user->page) {
            case User::PREPARING_TEST:
                if (!$this->message->document()) {
                    Telegraph::chat($chatId)->message("Iltimos, test yaratish uchun .docx formatdagi fayl yuboring!")->send();
                    return;
                }
                $this->makeQuestion($chatId, $this->message->document());
                break;
            case User::ENTER_TEST_NAME:
                if (!$this->check($text)) {
                    Telegraph::chat($chatId)->message("Siz <b>Bosh sahifa</b>da emassiz!\nIltimos, Test uchun nom kiriting!")->send();
                    return;
                }
                $this->verifyTestName($chatId, $text);
                break;
            case User::MAKE_STIKER:
                $this->generateStiker($chatId, $text);
                break;
            case User::ADD_RULE:
                $this->manageRule($chatId, $text, true);
                break;
            case User::REMOVE_RULE:
                $this->manageRule($chatId, $text, false);
                break;
            case User::HOME_PAGE:
                switch ($text) {
                    case "Stiker yaratish 🪄":
                        $this->sendStikerInfo($chatId);
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


    public function checkUserPage($chatId, $text)
    {
        $user = User::where('chat_id', $chatId)->first();
        if (!$user) {
            Telegraph::chat($chatId)->message("Foydalanuvchi topilmadi")->send();
            return;
        }

        if (($user->page != User::HOME_PAGE) && ($text == "To'lov 💳" || $text == "Stiker yaratish 🪄" || $text == "Test yaratish 📕" || $text == "Huquq berish 🔐" || $text == "Huquq olish 🔒")) {
            Telegraph::chat($chatId)->message("<b>Siz bosh sahifada emassiz!</b>")->send();
            return false;
        }
        return true;
    }

    private function sendStikerInfo($chatId)
    {
        Telegraph::chat($chatId)->message("<b>Iltimos, O'zingiz xohlagan stiker tavsifini yozing!</b>")->send();
        Telegraph::chat($chatId)->message("<b>Misol uchun:\n</b><code>Make me beauty cat stiker!</code>")->send();
        $this->updateUserPage($chatId, User::MAKE_STIKER);
        return;
    }


    public function sendMediaGroup(string|int $chatId, array $media)
    {
        if (empty($media)) {
            return null;
        }

        $parameters = [
            'chat_id' => $chatId,
            'media'   => json_encode($media, JSON_UNESCAPED_UNICODE)
        ];
        $this->updateUserPage($chatId, User::MAKE_STIKER);
        return $this->sendRequest('sendMediaGroup', $parameters);
    }

    public function sendRequest(string $method, array $parameters = [])
    {
        $apiUrl = "https://api.telegram.org/bot" . env('BOT_TOKEN') . "/";
        $url = $apiUrl . $method;

        $response = Http::withoutVerifying()
            ->timeout(30)
            ->asForm()
            ->post($url, $parameters);

        return $response->json();
    }


    private function generateStiker($chatId, $text)
    {
        $processing = Telegraph::chat($chatId)->message("⚡ <b>So'rovingiz qayta ishlanmoqda...</b>\n\n📝 <b>Tavsif:</b> <code>$text</code>")->send();
        $processingMessageId = $processing['result']['message_id'] ?? null;

        $response = Http::withoutVerifying()
            ->timeout(90)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])
            ->post(env('EXTERNAL_API_URL'), [
                'prompt' => $text
            ]);

        if (!$response->ok() || !$response->json('success')) {
            Telegraph::chat($chatId)->message("❌ <b>Yorliq yaratilmadi</b>\n\n📝 <b>Tavsif:</b> <code>$text</code>\n\n🔧 Yaxshiroq tavsif bilan qayta urinib ko‘ring.")->send();
            if ($processingMessageId) {
                Telegraph::chat($chatId)->deleteMessage($processingMessageId)->send();
            }

            return;
        }

        // 3) Rasm URL larni olish
        $images = $response->json('images') ?? $response->json('image_urls') ?? [];

        if (count($images) === 0) {
            Telegraph::chat($chatId)->message("❌ <b>Hech qanday yorliq qaytmadi</b>\n📝 <b>Tavsif:</b> <code>$text</code>")->send();

            if ($processingMessageId) {
                Telegraph::chat($chatId)->deleteMessage($processingMessageId)->send();
            }

            return;
        }

        $media = [];
        foreach ($images as $i => $img) {
            if ($i === 0) {
                $media[] = [
                    'type' => 'photo',
                    'media' => $img,
                    'caption' => "✅ " . count($images) . " ta yorliq tayyor!\n\n🎨 Tavsif: $text\n",
                    'parse_mode' => 'HTML',
                    'has_spoiler' => true,
                ];
            } else {
                $media[] = [
                    'type' => 'photo',
                    'media' => $img,
                    'has_spoiler' => true,
                ];
            }
        }

        $this->sendMediaGroup($chatId, $media);

        if ($processingMessageId) {
            Telegraph::chat($chatId)->deleteMessage($processingMessageId)->send();
        }
    }

    public function check($text)
    {
        if ($text == "To'lov 💳" || $text == "Qo'llanma ⭐️" || $text == "Admin bilan aloqa 📞" || $text == "Test yaratish 📕" || $text == "Huquq berish 🔐" || $text == "Huquq olish 🔒") {
            return false;
        }
        return true;
    }

    private function addRule($chatId, $addRule)
    {
        Telegraph::chat($chatId)->message("Iltimos, foydalanuvchi chat_id raqamini kiriting:")->send();
        $page = $addRule ? User::ADD_RULE : User::REMOVE_RULE;
        $this->updateUserPage($chatId, $page);
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
        $message = "Iltimos, Testlar yozilgan fayl yuboring";
        Telegraph::chat($chatId)->html($message)->send();
        $this->updateUserPage($chatId, User::PREPARING_TEST);
    }

    public function makeQuestion($chatId, $file)
    {
        $fileId = $file->id();
        $botToken = env('BOT_TOKEN');
        $response = Http::get("https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}");
        $fileInfo = $response->json();

        if (!$fileInfo['ok']) {
            throw new \Exception('Fayl ma\'lumotlarini olishda xato yuz berdi.');
        }

        $filePath = $fileInfo['result']['file_path'];
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if (strtolower($extension) !== 'docx') {
            Telegraph::chat($chatId)
                ->message('Iltimos, test yaratish uchun .docx formatdagi fayl yuboring!')
                ->send();
            return;
        }

        $filename = 'document_' . time() . '_' . uniqid() . '.' . $extension;

        $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

        $storagePath = "documents/$filename";

        $fileContent = file_get_contents($fileUrl);
        Storage::disk('public')->put($storagePath, $fileContent);

        if (!Storage::disk('public')->exists($storagePath)) {
            Telegraph::chat($chatId)
                ->message('Fayl topilmadi!')
                ->send();
            return;
        }

        Telegraph::chat($chatId)->message("⏳ Testlar yaratilishi boshlandi...")->send();
        $this->generateQuestion($chatId, $storagePath);
        $this->deleteFile($storagePath);
    }

    public function deleteFile(string $path)
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }


    public function generateQuestion($chatId, $storagePath)
    {
        $localPath = Storage::disk('public')->path($storagePath);

        $file = new \SplFileObject($localPath);

        DB::beginTransaction();

        try {

            $phpWord = IOFactory::load($file->getPathname());
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                        $text .= $element->getText() . "\n";
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $textElement->getText();
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            $textBody = preg_replace('/^\s*[\r\n]/m', '', $text);

            $prompt = env('PROMPT') . "\n\nHere is the content:\n$textBody";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY_FOR_QUESTION'),
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->failed()) {
                DB::rollBack();
                Telegraph::chat($chatId)->message("<b>Iltimos, qaytadan urinib ko'ring!</b>")->send();
                return;
            }

            $questionName = QuestionName::where('chat_id', $chatId)
                ->where('active', false)
                ->orderBy('id', 'desc')
                ->first();

            $formattedText = $response->json()['choices'][0]['message']['content'];

            $formattedText = preg_replace('/(?<!\n)([A-D]\))/', "\n$1", $formattedText);
            $formattedText = preg_replace('/(?<!\n)(Javob:)/', "\n$1", $formattedText);
            $formattedText = preg_replace('/[ \t]+/', ' ', $formattedText);
            $formattedText = preg_replace("/\n{2,}/", "\n", $formattedText);

            $fileContext = html_entity_decode(trim($formattedText), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $pattern = '/(.*?)\nA\)(.*?)\nB\)(.*?)\nC\)(.*?)\nD\)(.*?)\nJavob:\s*(.*?)(?=\n\d+\.\s|$)/s';
            preg_match_all($pattern, $fileContext, $matches, PREG_SET_ORDER);

            if (empty($matches)) {
                DB::rollBack();
                Telegraph::chat($chatId)->message("❌ Testlar strukturasida xatolik bor! Iltimos shablon bo‘yicha yuboring.")->send();
                return;
            }

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

            Question::insert($questions);

            $freeQuestionCount = QuestionName::where('chat_id', $chatId)
                ->where('active', true)->where('free', true)->count();

            $questionName->update([
                'active' => true,
                'free' => $freeQuestionCount < env('FREE_QUESTION_COUNT'),
            ]);

            DB::commit();

            $this->updateUserPage($chatId, User::HOME_PAGE);

            Telegraph::chat($chatId)->message("✅ Testlar muvaffaqqiyatli yaratildi!")->send();
            Telegraph::chat($chatId)->html("Yaratilgan testlarni <b>Test ishlash</b> bo‘limida ko‘rishingiz mumkin!")->send();
        } catch (\Exception $e) {
            DB::rollBack();
            Telegraph::chat($chatId)->message("❌ Xatolik: " . $e->getMessage())->send();
            return;
        }
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
