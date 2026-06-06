<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedInteger('ea_player_id');
            $table->string('name');
            $table->string('position', 4);
            $table->string('position_type', 12);                // goalkeeper|defender|midfielder|forward
            $table->unsignedTinyInteger('overall');
            $table->unsignedTinyInteger('pace')->nullable();
            $table->unsignedTinyInteger('shooting')->nullable();
            $table->unsignedTinyInteger('passing')->nullable();
            $table->unsignedTinyInteger('dribbling')->nullable();
            $table->unsignedTinyInteger('defending')->nullable();
            $table->unsignedTinyInteger('physical')->nullable();
            $table->unsignedTinyInteger('skill_moves')->nullable();
            $table->unsignedTinyInteger('weak_foot')->nullable();
            $table->string('nationality')->nullable();
            $table->string('nationality_image')->nullable();
            $table->string('avatar_url')->nullable();
            $table->date('birthdate')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'ea_player_id']);
            $table->index(['team_id', 'overall']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
