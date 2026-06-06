<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ties', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 8); // r16|qf|sf|final
            $table->unsignedTinyInteger('position'); // bracket slot within the stage
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete(); // leg-1 home side
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedTinyInteger('home_penalties')->nullable();
            $table->unsignedTinyInteger('away_penalties')->nullable();
            $table->timestamps();

            $table->unique(['stage', 'position']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreign('tie_id')->references('id')->on('ties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['tie_id']);
        });

        Schema::dropIfExists('ties');
    }
};
