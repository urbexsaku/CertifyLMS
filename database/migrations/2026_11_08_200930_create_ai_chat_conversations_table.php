<?php

declare(strict_types=1);

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
        Schema::create('ai_chat_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('title', 100);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('title_generated_at')->nullable();
            $table->timestamp('title_manually_edited_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_conversations');
    }
};
