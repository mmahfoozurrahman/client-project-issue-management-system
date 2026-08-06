<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_ai_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('source_tool', 50);
            $table->string('model')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('external_source_id', 191);
            $table->string('repository')->nullable();
            $table->string('git_branch')->nullable();
            $table->string('commit_hash', 255)->nullable();
            $table->timestamps();

            $table->unique(['source_tool', 'external_source_id']);
            $table->unique('issue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_ai_sources');
    }
};
