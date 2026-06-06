<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->boolean('is_starting');
            $table->unsignedTinyInteger('came_on')->nullable();  // sub-in minute
            $table->unsignedTinyInteger('went_off')->nullable(); // sub-out / red / injury minute
            $table->decimal('rating', 3, 1)->nullable();         // match performance, max 10
            $table->timestamps();

            $table->unique(['game_id', 'player_id']);
            $table->index(['player_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appearances');
    }
};
