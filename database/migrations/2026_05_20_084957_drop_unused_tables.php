<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('media');
    }

    public function down(): void
    {
        // intentionally not restored — these features were removed
    }
};
