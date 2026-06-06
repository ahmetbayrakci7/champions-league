<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            // Localisable commentary: template key + name params let the
            // frontend rebuild the sentence in any language while the
            // stored `commentary` stays as the English fallback.
            $table->string('template', 24)->nullable()->after('commentary');
            $table->json('params')->nullable()->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropColumn(['template', 'params']);
        });
    }
};
