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
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('user1_cleared_at')->nullable()->after('deleted_by_user2');
            $table->timestamp('user2_cleared_at')->nullable()->after('user1_cleared_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['user1_cleared_at', 'user2_cleared_at']);
        });
    }
};
