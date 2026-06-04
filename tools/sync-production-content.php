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
    ->map(fn (int $index) => '/videos/generated/lesson-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.webm')
    ->all();
$lessonImageFor = fn (string $video): string => str_replace(['/videos/generated/', '.webm'], ['/images/lessons/', '.jpg'], $video);
$lessonVideoPools = [
    'osnovy-gitary' => [$lessonVideos[0], $lessonVideos[1], $lessonVideos[2], $lessonVideos[3], $lessonVideos[4]],
    'start-na-fortepiano' => [$lessonVideos[5], $lessonVideos[6]],
    'praktika-na-udarnyh' => [$lessonVideos[7], $lessonVideos[8], $lessonVideos[9]],
    'trenirovka-vokala' => [$lessonVideos[10], $lessonVideos[11]],
    'kurs-ukulele' => [$lessonVideos[9], $lessonVideos[3], $lessonVideos[4]],
    'muzykalnaya-teoriya' => [$lessonVideos[11], $lessonVideos[5], $lessonVideos[6]],
];
$communityVideos = collect(range(1, 12))
    ->map(fn (int $index) => '/videos/community/community-video-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.webm')
    ->all();
$communityImages = collect(range(1, 12))
    ->map(fn (int $index) => '/images/community/community-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.jpg')
    ->all();
$communityVideoContent = [
    ['title' => 'Ровный бой: упражнение 1', 'description' => 'Короткая запись с базовым strumming-паттерном. Следите за равномерностью правой руки.', 'instrument' => 'Гитара'],
    ['title' => 'Бой без остановок', 'description' => 'Тренировка непрерывного движения кисти на простом гитарном ритме.', 'instrument' => 'Гитара'],
    ['title' => 'Ритм на открытых струнах', 'description' => 'Практика ровной атаки и одинаковой громкости на каждом ударе.', 'instrument' => 'Гитара'],
    ['title' => 'Медленный strumming', 'description' => 'Разбор спокойного темпа: удобно повторять вместе с метрономом.', 'instrument' => 'Гитара'],
    ['title' => 'Подготовка к бою', 'description' => 'Упражнение для правой руки перед переходом к песням и аккордовым связкам.', 'instrument' => 'Гитара'],
    ['title' => 'Акцент на сильную долю', 'description' => 'Небольшой ритмический фрагмент, чтобы почувствовать первую долю такта.', 'instrument' => 'Гитара'],
    ['title' => 'Бой с устойчивым темпом', 'description' => 'Повторяющийся рисунок для контроля темпа и расслабленной кисти.', 'instrument' => 'Гитара'],
    ['title' => 'Галоп на гитаре', 'description' => 'Более энергичный strumming-паттерн для тренировки плотного движения.', 'instrument' => 'Гитара'],
    ['title' => 'Регги-ритм правой рукой', 'description' => 'Практика смещенного акцента и коротких приглушенных движений.', 'instrument' => 'Гитара'],
    ['title' => 'Песенный бой в среднем темпе', 'description' => 'Ритм для аккомпанемента: держите ровный пульс от начала до конца.', 'instrument' => 'Гитара'],
    ['title' => 'Звучание старого пианино', 'description' => 'Короткий фрагмент с акустическим инструментом: слушайте динамику и атаку.', 'instrument' => 'Фортепиано'],
    ['title' => 'Фортепиано в четыре руки', 'description' => 'Пример ансамблевой игры: обратите внимание на синхронность партий.', 'instrument' => 'Фортепиано'],
];

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

    $pool = $lessonVideoPools[$courseData['code']] ?? $lessonVideos;

    $course->update([
        'code' => $courseData['code'],
        'user_id' => $teacher->id,
        'author' => $teacher->name,
        'video' => $pool[0],
    ]);

    foreach ($course->lessonList as $index => $lesson) {
        $video = $pool[$index % count($pool)];
        $lesson->update([
            'code' => 'course-'.$courseData['code'].'-'.($index + 1),
            'image' => $lessonImageFor($video),
            'video' => $video,
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
    ->limit(12)
    ->get()
    ->each(function (UserVideo $video, int $index) use ($communityImages, $communityVideos, $communityVideoContent, $instrumentIdsByName) {
        $content = $communityVideoContent[$index];
        $video->update([
            'title' => $content['title'],
            'description' => $content['description'],
            'instrument' => $content['instrument'],
            'instrument_id' => $instrumentIdsByName[$content['instrument']] ?? null,
            'status' => 'опубликовано',
            'image' => $communityImages[$index % count($communityImages)],
            'video' => $communityVideos[$index % count($communityVideos)],
        ]);
    });

UserVideo::query()
    ->whereNotIn('id', UserVideo::query()->orderBy('id')->limit(12)->pluck('id'))
    ->delete();

echo "Production content synced.\n";
