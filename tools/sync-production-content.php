<?php

use App\Models\Course;
use App\Models\Instrument;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserVideo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$lessonVideos = collect(range(1, 12))
    ->map(fn (int $index) => '/videos/generated/lesson-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.mp4')
    ->all();
$communityImages = collect(range(1, 20))
    ->map(fn (int $index) => '/images/community/community-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.jpg')
    ->all();

$instrumentIdsByName = Instrument::query()->pluck('id', 'name');
$teachersByName = collect([
    ['name' => 'Антон Лебедев', 'email' => 'anton.teacher@example.com', 'instrument' => 'Гитара'],
    ['name' => 'Мария Соколова', 'email' => 'maria.teacher@example.com', 'instrument' => 'Фортепиано'],
    ['name' => 'Денис Орлов', 'email' => 'denis.teacher@example.com', 'instrument' => 'Ударные'],
    ['name' => 'Елена Миронова', 'email' => 'elena.teacher@example.com', 'instrument' => 'Вокал'],
    ['name' => 'Кира Волкова', 'email' => 'kira.teacher@example.com', 'instrument' => 'Укулеле'],
    ['name' => 'Илья Ветров', 'email' => 'ilya.teacher@example.com', 'instrument' => 'Любой инструмент'],
])->mapWithKeys(function (array $teacher) use ($instrumentIdsByName) {
    $existing = User::query()->where('email', $teacher['email'])->first();
    $user = User::query()->updateOrCreate(
        ['email' => $teacher['email']],
        [
            'name' => $teacher['name'],
            'password' => $existing?->password ?? Hash::make('teacher123'),
            'role' => 'teacher',
            'teacher_status' => $existing?->teacher_status ?? 'ожидает',
            'instrument' => $teacher['instrument'],
            'is_banned' => $existing?->is_banned ?? false,
        ],
    );

    if ($teacher['instrument'] !== 'Любой инструмент') {
        $instrumentId = $instrumentIdsByName[$teacher['instrument']] ?? null;
        $user->instruments()->sync($instrumentId ? [$instrumentId] : []);
    }

    return [$teacher['name'] => $user];
});

$courseTeachers = [
    ['legacy' => '01', 'code' => 'osnovy-gitary', 'teacher' => 'Антон Лебедев'],
    ['legacy' => '02', 'code' => 'start-na-fortepiano', 'teacher' => 'Мария Соколова'],
    ['legacy' => '03', 'code' => 'praktika-na-udarnyh', 'teacher' => 'Денис Орлов'],
    ['legacy' => '04', 'code' => 'trenirovka-vokala', 'teacher' => 'Елена Миронова'],
    ['legacy' => '05', 'code' => 'kurs-ukulele', 'teacher' => 'Кира Волкова'],
    ['legacy' => '06', 'code' => 'muzykalnaya-teoriya', 'teacher' => 'Илья Ветров'],
];

foreach ($courseTeachers as $courseIndex => $courseData) {
    $teacher = $teachersByName[$courseData['teacher']] ?? null;
    $course = Course::query()
        ->with('lessonList')
        ->whereIn('code', [$courseData['legacy'], $courseData['code']])
        ->first();

    if (! $course || ! $teacher) {
        continue;
    }

    $course->update([
        'code' => $courseData['code'],
        'user_id' => $teacher->id,
        'author' => $teacher->name,
    ]);

    foreach ($course->lessonList as $index => $lesson) {
        $lesson->update([
            'code' => 'course-'.$courseData['code'].'-'.($index + 1),
            'video' => $lessonVideos[(($index + ($courseIndex * 2)) % count($lessonVideos))],
        ]);
    }
}

foreach ($courseTeachers as $courseData) {
    DB::table('platform_comments')
        ->where('target_type', 'course')
        ->where('target_code', $courseData['legacy'])
        ->update(['target_code' => $courseData['code']]);
}

UserVideo::query()
    ->orderBy('id')
    ->get()
    ->each(function (UserVideo $video, int $index) use ($communityImages, $lessonVideos) {
        $video->update([
            'image' => $communityImages[$index % count($communityImages)],
            'video' => $lessonVideos[$index % count($lessonVideos)],
        ]);
    });

echo "Production content synced.\n";
