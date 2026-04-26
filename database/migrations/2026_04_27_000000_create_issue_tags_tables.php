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
        Schema::create('issue_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->index('project_id');
        });

        Schema::create('issue_issue_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['issue_id', 'issue_tag_id']);
            $table->index('issue_id');
            $table->index('issue_tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_issue_tag');
        Schema::dropIfExists('issue_tags');
    }
};
