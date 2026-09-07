<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('issue_narrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 50)->default('gemini');
            $table->string('locale', 20)->default('bn-IN');
            $table->string('voice_name')->nullable();
            $table->string('source_hash', 64)->index();
            $table->string('status', 20)->default('idle');
            $table->json('audio_paths')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_narrations');
    }
};
