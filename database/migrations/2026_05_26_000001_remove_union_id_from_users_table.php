<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'unionId')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('unionId');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'unionId')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('unionId')->nullable()->unique()->after('id');
        });
    }
};
