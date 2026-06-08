<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('teacher_status');
            }
        });

        foreach (['courses', 'user_videos', 'platform_comments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['platform_comments', 'user_videos', 'courses', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
            });
        }
    }
};
