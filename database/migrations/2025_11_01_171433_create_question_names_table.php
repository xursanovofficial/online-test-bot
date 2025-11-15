<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('question_names', function (Blueprint $table) {
            $table->id();
            $table->string("question_name");
            $table->string("chat_id");
            $table->boolean("active")->default(false);
            $table->boolean("free");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_names');
    }
};
