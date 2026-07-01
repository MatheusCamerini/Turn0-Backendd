<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sheet_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('character_name');
            $table->json('data');
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id', 'sheet_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_sheets');
    }
};
