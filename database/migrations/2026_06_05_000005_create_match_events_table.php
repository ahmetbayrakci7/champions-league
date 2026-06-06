<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->cascadeOnDelete();
            $table->foreignId('related_player_id')->nullable()->constrained('players')->cascadeOnDelete();
            $table->unsignedTinyInteger('minute');
            $table->string('type', 12); // goal|yellow|red|injury|sub
            $table->string('commentary');
            $table->timestamps();

            $table->index(['game_id', 'minute']);
            $table->index(['player_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
