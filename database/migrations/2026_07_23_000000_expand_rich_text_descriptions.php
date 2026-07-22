<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->mediumText('description')->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->mediumText('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }
};
