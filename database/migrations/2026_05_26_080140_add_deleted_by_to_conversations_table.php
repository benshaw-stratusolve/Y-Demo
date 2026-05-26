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
            $table->boolean('deleted_by_user1')->default(false)->after('user2_id');
            $table->boolean('deleted_by_user2')->default(false)->after('deleted_by_user1');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['deleted_by_user1', 'deleted_by_user2']);
        });
    }
};
