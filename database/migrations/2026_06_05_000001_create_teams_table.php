<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 3)->unique();
            $table->string('color', 7)->default('#16a34a');
            $table->string('country', 3);                       // association, e.g. ENG — same-country clash rule
            $table->unsignedTinyInteger('pot');                 // seeding pot 1-4
            $table->unsignedInteger('ea_team_id')->nullable()->unique();
            $table->string('logo_url')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->unsignedTinyInteger('power');               // 1-100, derived from squad overalls
            $table->unsignedTinyInteger('home_advantage');      // 0-20 % boost when at home
            $table->unsignedTinyInteger('supporter_strength');  // 0-100
            $table->unsignedTinyInteger('goalkeeper_factor');   // 0-100, best keeper's overall
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
