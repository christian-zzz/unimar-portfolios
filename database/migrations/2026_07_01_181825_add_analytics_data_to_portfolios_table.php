<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("portfolios", function (Blueprint $table) {
            $table->jsonb("analytics_data")->nullable()->after("lighthouse_scores");
            $table->timestamp("last_analytics_updated_at")->nullable()->after("analytics_data");
        });
    }

    public function down(): void
    {
        Schema::table("portfolios", function (Blueprint $table) {
            $table->dropColumn(["analytics_data", "last_analytics_updated_at"]);
        });
    }
};
