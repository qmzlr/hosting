<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lessons', 'image')) {
            return;
        }

        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lessons', 'image')) {
            return;
        }

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('image');
        });
    }
};
