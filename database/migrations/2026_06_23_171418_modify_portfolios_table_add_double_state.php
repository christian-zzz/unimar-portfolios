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
        Schema::table('portfolios', function (Blueprint $table) {
            $table->renameColumn('content', 'draft_content');
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->jsonb('published_content')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn('published_content');
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->renameColumn('draft_content', 'content');
        });
    }
};
