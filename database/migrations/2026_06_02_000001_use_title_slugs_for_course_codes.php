<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $courseCodes = [
        '01' => 'osnovy-gitary',
        '02' => 'start-na-fortepiano',
        '03' => 'praktika-na-udarnyh',
        '04' => 'trenirovka-vokala',
        '05' => 'kurs-ukulele',
        '06' => 'muzykalnaya-teoriya',
    ];

    public function up(): void
    {
        $this->resizeCodeColumns();
        $this->updateCourseCodes($this->courseCodes);
    }

    public function down(): void
    {
        $this->updateCourseCodes(array_flip($this->courseCodes));
    }

    private function resizeCodeColumns(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE courses MODIFY code VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE lessons MODIFY code VARCHAR(96) NOT NULL');
    }

    private function updateCourseCodes(array $codes): void
    {
        foreach ($codes as $from => $to) {
            $course = DB::table('courses')->where('code', $from)->first(['id']);

            if (! $course) {
                continue;
            }

            DB::table('courses')
                ->where('id', $course->id)
                ->update(['code' => $to]);

            DB::table('platform_comments')
                ->where('target_type', 'course')
                ->where('target_code', $from)
                ->update(['target_code' => $to]);

            DB::table('lessons')
                ->where('course_id', $course->id)
                ->orderBy('position')
                ->get(['id'])
                ->values()
                ->each(function ($lesson, int $index) use ($to): void {
                    DB::table('lessons')
                        ->where('id', $lesson->id)
                        ->update(['code' => 'course-'.$to.'-'.($index + 1)]);
                });
        }
    }
};
