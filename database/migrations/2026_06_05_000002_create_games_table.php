<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->string('stage', 8)->default('group'); // group|r16|qf|sf|final
            $table->unsignedBigInteger('tie_id')->nullable()->index();
            $table->unsignedTinyInteger('leg')->nullable(); // 1|2 for two-legged ties
            $table->unsignedTinyInteger('week');
            $table->dateTime('kickoff_at')->nullable();
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();
            $table->boolean('is_played')->default(false);
            $table->timestamps();

            $table->index(['week', 'is_played']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
