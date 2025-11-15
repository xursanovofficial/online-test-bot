<?php

namespace App\Console\Commands;

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Console\Command;

class MakeWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = env('BOT_NAME');
        if (empty($name)) {
            $this->error('Bot nomi topilmadi!');
            return;
        }
        $existingBot = TelegraphBot::where('token', env('BOT_TOKEN'))
            ->where('name', $name)
            ->first();
        if ($existingBot) {
            $existingBot->registerWebhook()->send();
            $this->info('Bunday bot mavjud!');
            return;
        }
        $bot = TelegraphBot::create([
            'token' => env('BOT_TOKEN'),
            'name' => $name
        ]);
        $bot->registerWebhook()->send();
        $this->info('Webhook muvaffaqiyatli o\'rnatildi!');
    }
}
