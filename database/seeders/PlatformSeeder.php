<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Instrument;
use App\Models\Lesson;
use App\Models\PlatformComment;
use App\Models\User;
use App\Models\UserVideo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $lessonVideos = collect(range(1, 12))
            ->map(fn (int $index) => '/videos/generated/lesson-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.webm')
            ->all();
        $lessonImageFor = fn (string $video, string $courseImage): string => str_starts_with($video, '/videos/generated/')
            ? str_replace(['/videos/generated/', '.webm'], ['/images/lessons/', '.jpg'], $video)
            : $courseImage;
        $lessonVideoPools = [
            'osnovy-gitary' => [$lessonVideos[0], $lessonVideos[1], $lessonVideos[2], $lessonVideos[3], $lessonVideos[4]],
            'start-na-fortepiano' => [$lessonVideos[5], $lessonVideos[6]],
            'praktika-na-udarnyh' => [$lessonVideos[7], $lessonVideos[8], $lessonVideos[9]],
            'trenirovka-vokala' => ['/videos/lessons/vocal-warmup.webm', '/videos/lessons/vocal-overtone-singing.webm'],
            'kurs-ukulele' => ['/videos/lessons/ukulele-kumalae.webm', $lessonVideos[9]],
            'muzykalnaya-teoriya' => ['/videos/lessons/theory-rhythm.webm', '/videos/lessons/theory-melody.webm', $lessonVideos[11]],
        ];
        $communityVideos = collect(range(1, 12))
            ->map(fn (int $index) => '/videos/community/community-video-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.webm')
            ->all();
        $communityImages = collect(range(1, 12))
            ->map(fn (int $index) => '/images/community/community-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.jpg')
            ->all();

        $instruments = [
            ['slug' => 'guitar', 'name' => 'Гитара', 'image' => '/images/course-guitar.jpg', 'description' => 'Акустика, аккорды, ритм и песни для самостоятельной практики.', 'course_count' => 1],
            ['slug' => 'piano', 'name' => 'Фортепиано', 'image' => '/images/course-piano.jpg', 'description' => 'Клавиши, координация рук, слух и музыкальная теория.', 'course_count' => 1],
            ['slug' => 'drums', 'name' => 'Ударные', 'image' => '/images/course-drums.jpg', 'description' => 'Ритм, координация, grooves и тренировка с метрономом.', 'course_count' => 1],
            ['slug' => 'vocal', 'name' => 'Вокал', 'image' => '/images/course-vocal.jpg', 'description' => 'Дыхание, диапазон, интонация и запись домашней практики.', 'course_count' => 1],
            ['slug' => 'ukulele', 'name' => 'Укулеле', 'image' => '/images/course-ukulele.jpg', 'description' => 'Лёгкий старт, аккорды, переборы и быстрый репертуар.', 'course_count' => 1],
            ['slug' => 'theory', 'name' => 'Теория', 'image' => '/images/course-theory.jpg', 'description' => 'Ноты, гармония, интервалы и понимание структуры музыки.', 'course_count' => 1],
        ];

        foreach ($instruments as $instrument) {
            Instrument::query()->updateOrCreate(
                ['slug' => $instrument['slug']],
                $instrument,
            );
        }

        $instrumentIdsByName = Instrument::query()->pluck('id', 'name');
        $teachersByName = collect([
            ['name' => 'Антон Лебедев', 'email' => 'anton.teacher@example.com', 'instrument' => 'Гитара'],
            ['name' => 'Мария Соколова', 'email' => 'maria.teacher@example.com', 'instrument' => 'Фортепиано'],
            ['name' => 'Денис Орлов', 'email' => 'denis.teacher@example.com', 'instrument' => 'Ударные'],
            ['name' => 'Елена Миронова', 'email' => 'elena.teacher@example.com', 'instrument' => 'Вокал'],
            ['name' => 'Кира Волкова', 'email' => 'kira.teacher@example.com', 'instrument' => 'Укулеле'],
            ['name' => 'Илья Ветров', 'email' => 'ilya.teacher@example.com', 'instrument' => 'Любой инструмент'],
        ])->mapWithKeys(function (array $teacher) use ($instrumentIdsByName) {
            $user = User::query()->updateOrCreate(
                ['email' => $teacher['email']],
                [
                    'name' => $teacher['name'],
                    'password' => Hash::make('teacher123'),
                    'role' => 'teacher',
                    'teacher_status' => 'одобрен',
                    'instrument' => $teacher['instrument'],
                    'is_banned' => false,
                    'lastSignInAt' => now(),
                ],
            );

            if ($teacher['instrument'] !== 'Любой инструмент') {
                $instrumentId = $instrumentIdsByName[$teacher['instrument']] ?? null;
                $user->instruments()->sync($instrumentId ? [$instrumentId] : []);
            }

            return [$teacher['name'] => $user];
        });

        $courses = [
            [
                'legacy_code' => '01',
                'code' => 'osnovy-gitary',
                'title' => 'Основы гитары',
                'author' => 'Антон Лебедев',
                'category' => 'Основы',
                'instrument' => 'Гитара',
                'image' => '/images/course-guitar.jpg',
                'tagline' => 'Освойте акустическую гитару с нуля — от первых аккордов до уверенного перебора.',
                'short_description' => 'Первые аккорды, посадка, бой, перебор и уверенный старт на акустике.',
                'description' => [
                    'Курс построен вокруг ежедневной практики: короткие видеоуроки, упражнения на технику и разбор популярных песен.',
                    'К концу программы вы будете уверенно перебирать, играть боем и сопровождать любимые треки.',
                ],
                'features' => ['8 практических видеоуроков для уверенного старта', 'Интерактивные табулатуры', 'Упражнения с метрономом', 'Разбор первой песни'],
                'outcomes' => ['Настраивать инструмент', 'Играть базовые аккорды', 'Держать ритм под метроном', 'Разбирать песни самостоятельно'],
                'lessons' => '8 уроков',
                'lesson_count' => 8,
                'level' => 'Начинающий',
                'duration' => '8 недель',
                'duration_weeks' => 8,
                'progress' => 42,
                'video' => $lessonVideos[0],
                'lesson_titles' => ['Посадка и настройка', 'Первые аккорды', 'Смена аккордов', 'Ритмический бой', 'Перебор и динамика', 'Игра под метроном', 'Разбор куплета', 'Первая песня целиком'],
            ],
            [
                'legacy_code' => '02',
                'code' => 'start-na-fortepiano',
                'title' => 'Старт на фортепиано',
                'author' => 'Мария Соколова',
                'category' => 'Основы',
                'instrument' => 'Фортепиано',
                'image' => '/images/course-piano.jpg',
                'tagline' => 'Начните играть на пианино с правильной техникой и музыкальным слухом.',
                'short_description' => 'Клавиши, слух, координация рук и первые мелодии с аккомпанементом.',
                'description' => ['Этот курс раскрывает фортепиано как инструмент для самовыражения.', 'Программа включает нотную грамоту, ритмику и интервалы через практику.'],
                'features' => ['6 видеоуроков с несколькими ракурсами', 'Виртуальная клавиатура', 'Упражнения на независимость рук', 'PDF-конспекты'],
                'outcomes' => ['Читать простую партию', 'Играть двумя руками', 'Понимать интервалы', 'Разбирать мелодии'],
                'lessons' => '6 уроков',
                'lesson_count' => 6,
                'level' => 'Начинающий',
                'duration' => '10 недель',
                'duration_weeks' => 10,
                'progress' => 28,
                'video' => $lessonVideos[1],
                'lesson_titles' => ['Посадка за инструментом', 'Первые клавиши', 'Нотная ориентация', 'Ритм двумя руками', 'Простая гармония', 'Мини-репертуар'],
            ],
            [
                'legacy_code' => '03',
                'code' => 'praktika-na-udarnyh',
                'title' => 'Практика на ударных',
                'author' => 'Денис Орлов',
                'category' => 'Ритм',
                'instrument' => 'Ударные',
                'image' => '/images/course-drums.jpg',
                'tagline' => 'Почувствуйте ритм изнутри — от первого бита до уверенных grooves.',
                'short_description' => 'Координация, базовые рисунки, fills и практика с метрономом.',
                'description' => ['Курс для тех, кто хочет освоить барабаны и развить внутренний ритм.', 'Особое внимание уделено работе с метрономом, динамике и заполнениям.'],
                'features' => ['7 видеоуроков', 'Встроенный метроном', 'Библиотека минусовок', 'Разбор grooves'],
                'outcomes' => ['Держать ровный groove', 'Играть fills', 'Тренироваться с кликом', 'Собирать партии под минус'],
                'lessons' => '7 уроков',
                'lesson_count' => 7,
                'level' => 'Начинающий',
                'duration' => '8 недель',
                'duration_weeks' => 8,
                'progress' => 64,
                'video' => $lessonVideos[2],
                'lesson_titles' => ['Работа рук', 'Первый beat', 'Ноги и координация', 'Акценты хай-хэта', 'Fills', 'Переходы между частями', 'Игра под минус'],
            ],
            [
                'legacy_code' => '04',
                'code' => 'trenirovka-vokala',
                'title' => 'Тренировка вокала',
                'author' => 'Елена Миронова',
                'category' => 'Техника',
                'instrument' => 'Вокал',
                'image' => '/images/course-vocal.jpg',
                'tagline' => 'Откройте свой голос — дыхание, техника, уверенность на сцене.',
                'short_description' => 'Дыхание, резонаторы, диапазон и уверенное исполнение песен.',
                'description' => ['Комплексный вокальный курс для начинающих певцов.', 'Каждый модуль завершается записью вокальной партии с персональной обратной связью.'],
                'features' => ['5 видеоуроков', 'Ежедневные вокальные разминки', 'Упражнения на диапазон', 'Запись вокала'],
                'outcomes' => ['Дышать диафрагмой', 'Петь чище', 'Расширять диапазон', 'Записывать домашние задания'],
                'lessons' => '5 уроков',
                'lesson_count' => 5,
                'level' => 'Начинающий',
                'duration' => '10 недель',
                'duration_weeks' => 10,
                'progress' => 18,
                'video' => $lessonVideos[0],
                'lesson_titles' => ['Дыхание', 'Разминка', 'Резонаторы', 'Чистая интонация', 'Запись голоса'],
            ],
            [
                'legacy_code' => '05',
                'code' => 'kurs-ukulele',
                'title' => 'Курс укулеле',
                'author' => 'Кира Волкова',
                'category' => 'Песни',
                'instrument' => 'Укулеле',
                'image' => '/images/course-ukulele.jpg',
                'tagline' => 'Лёгкий и радостный старт — укулеле для начинающих.',
                'short_description' => 'Компактные уроки, аккорды, переборы и первые песни за несколько занятий.',
                'description' => ['Укулеле — идеальный инструмент для быстрого старта в музыке.', 'Программа включает fingerstyle, боевые техники, переборы и простые соло.'],
                'features' => ['4 коротких видеоурока', 'Каталог аккордов', 'Разбор песен', 'Трекер ежедневной практики'],
                'outcomes' => ['Играть простые песни', 'Зажимать аккорды', 'Использовать перебор', 'Практиковаться короткими сессиями'],
                'lessons' => '4 урока',
                'lesson_count' => 4,
                'level' => 'Начинающий',
                'duration' => '6 недель',
                'duration_weeks' => 6,
                'progress' => 76,
                'video' => $lessonVideos[1],
                'lesson_titles' => ['Строй укулеле', 'Аккорды C F G', 'Первый бой', 'Песня целиком'],
            ],
            [
                'legacy_code' => '06',
                'code' => 'muzykalnaya-teoriya',
                'title' => 'Музыкальная теория',
                'author' => 'Илья Ветров',
                'category' => 'Теория',
                'instrument' => 'Любой инструмент',
                'image' => '/images/course-theory.jpg',
                'tagline' => 'Поймите музыку изнутри — ноты, гармония, структура композиций.',
                'short_description' => 'Ноты, интервалы, аккорды, тональности и теория через практические примеры.',
                'description' => ['Теория музыки становится понятной, когда изучается через практику.', 'Вы научитесь читать ноты, строить аккордовые последовательности и понимать лад.'],
                'features' => ['6 видеоуроков', 'Визуализация интервалов', 'Тренажёр чтения нот', 'PDF-справочники'],
                'outcomes' => ['Читать ноты', 'Строить аккорды', 'Понимать тональности', 'Анализировать песни'],
                'lessons' => '6 уроков',
                'lesson_count' => 6,
                'level' => 'Базовый',
                'duration' => '8 недель',
                'duration_weeks' => 8,
                'progress' => 35,
                'video' => $lessonVideos[2],
                'lesson_titles' => ['Нотный стан', 'Интервалы', 'Трезвучия', 'Аккорды', 'Тональности', 'Гармония песни'],
            ],
        ];

        foreach ($courses as $courseIndex => $courseData) {
            $lessonTitles = $courseData['lesson_titles'];
            $legacyCode = $courseData['legacy_code'];
            $pool = $lessonVideoPools[$courseData['code']] ?? $lessonVideos;
            $courseData['video'] = $pool[0];
            unset($courseData['lesson_titles'], $courseData['legacy_code']);
            $owner = $teachersByName[$courseData['author']] ?? null;
            $courseData['user_id'] = $owner?->id;
            $courseData['author'] = $owner?->name ?? $courseData['author'];

            $course = Course::query()
                ->whereIn('code', [$legacyCode, $courseData['code']])
                ->first();

            $coursePayload = [
                ...$courseData,
                'instrument_id' => $instrumentIdsByName[$courseData['instrument']] ?? null,
            ];

            if ($course) {
                $course->update($coursePayload);
            } else {
                $course = Course::query()->create($coursePayload);
            }

            $course->lessonList()->delete();

            foreach ($lessonTitles as $index => $title) {
                Lesson::query()->create([
                    'course_id' => $course->id,
                    'code' => 'course-'.$course->code.'-'.($index + 1),
                    'title' => $title,
                    'description' => 'Короткий практический урок с демонстрацией, заданием для самостоятельной работы и проверкой ритма.',
                    'duration' => (10 + $index * 3).' мин',
                    'image' => $lessonImageFor($pool[$index % count($pool)], $courseData['image']),
                    'video' => $pool[$index % count($pool)],
                    'completed' => $index < 2,
                    'position' => $index + 1,
                ]);
            }
        }

        $videos = [
            ['title' => 'Ровный бой: упражнение 1', 'description' => 'Короткая запись с базовым strumming-паттерном. Следите за равномерностью правой руки.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Бой без остановок', 'description' => 'Тренировка непрерывного движения кисти на простом гитарном ритме.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Ритм на открытых струнах', 'description' => 'Практика ровной атаки и одинаковой громкости на каждом ударе.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Медленный strumming', 'description' => 'Разбор спокойного темпа: удобно повторять вместе с метрономом.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Подготовка к бою', 'description' => 'Упражнение для правой руки перед переходом к песням и аккордовым связкам.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Акцент на сильную долю', 'description' => 'Небольшой ритмический фрагмент, чтобы почувствовать первую долю такта.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Бой с устойчивым темпом', 'description' => 'Повторяющийся рисунок для контроля темпа и расслабленной кисти.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Галоп на гитаре', 'description' => 'Более энергичный strumming-паттерн для тренировки плотного движения.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Регги-ритм правой рукой', 'description' => 'Практика смещенного акцента и коротких приглушенных движений.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Песенный бой в среднем темпе', 'description' => 'Ритм для аккомпанемента: держите ровный пульс от начала до конца.', 'instrument' => 'Гитара', 'status' => 'опубликовано', 'image' => '/images/course-guitar.jpg'],
            ['title' => 'Звучание старого пианино', 'description' => 'Короткий фрагмент с акустическим инструментом: слушайте динамику и атаку.', 'instrument' => 'Фортепиано', 'status' => 'опубликовано', 'image' => '/images/course-piano.jpg'],
            ['title' => 'Фортепиано в четыре руки', 'description' => 'Пример ансамблевой игры: обратите внимание на синхронность партий.', 'instrument' => 'Фортепиано', 'status' => 'опубликовано', 'image' => '/images/course-piano.jpg'],
        ];

        UserVideo::query()
            ->whereNotIn('title', collect($videos)->pluck('title')->all())
            ->delete();

        foreach ($videos as $index => $video) {
            UserVideo::query()->updateOrCreate(
                ['title' => $video['title']],
                [
                    'userId' => null,
                    ...$video,
                    'instrument_id' => $instrumentIdsByName[$video['instrument']] ?? null,
                    'image' => $communityImages[$index % count($communityImages)],
                    'video' => $communityVideos[$index % count($communityVideos)],
                ],
            );
        }

        $comments = [
            ['author' => 'Мария', 'text' => 'Очень помогло упражнение с метрономом.', 'target' => 'Практика на ударных', 'target_type' => 'course', 'target_code' => 'praktika-na-udarnyh', 'status' => 'ожидает'],
            ['author' => 'Илья', 'text' => 'Добавил бы больше разборов песен.', 'target' => 'Основы гитары', 'target_type' => 'course', 'target_code' => 'osnovy-gitary', 'status' => 'ожидает'],
            ['author' => 'Аня', 'text' => 'Не поняла задание к третьему уроку.', 'target' => 'Старт на фортепиано', 'target_type' => 'course', 'target_code' => 'start-na-fortepiano', 'status' => 'ожидает'],
            ['author' => 'Олег', 'text' => 'После восьми коротких уроков стало проще держать ритм и не сбиваться на смене аккордов.', 'target' => 'Основы гитары', 'target_type' => 'course', 'target_code' => 'osnovy-gitary', 'status' => 'одобрено'],
            ['author' => 'Лена', 'text' => 'Понравились упражнения на две руки: уроки короткие, но прогресс заметен сразу.', 'target' => 'Старт на фортепиано', 'target_type' => 'course', 'target_code' => 'start-na-fortepiano', 'status' => 'одобрено'],
            ['author' => 'Максим', 'text' => 'Хорошая подача по координации, особенно блок с хай-хэтом и переходами.', 'target' => 'Практика на ударных', 'target_type' => 'course', 'target_code' => 'praktika-na-udarnyh', 'status' => 'одобрено'],
            ['author' => 'София', 'text' => 'Разминки и запись голоса помогли услышать, где интонация плавает.', 'target' => 'Тренировка вокала', 'target_type' => 'course', 'target_code' => 'trenirovka-vokala', 'status' => 'одобрено'],
            ['author' => 'Никита', 'text' => 'Курс быстрый и понятный: уже после первых занятий можно сыграть песню целиком.', 'target' => 'Курс укулеле', 'target_type' => 'course', 'target_code' => 'kurs-ukulele', 'status' => 'одобрено'],
            ['author' => 'Вера', 'text' => 'Теория наконец связалась с практикой, особенно темы интервалов и тональностей.', 'target' => 'Музыкальная теория', 'target_type' => 'course', 'target_code' => 'muzykalnaya-teoriya', 'status' => 'одобрено'],
        ];

        foreach ($comments as $comment) {
            $courseId = Course::query()->where('code', $comment['target_code'])->value('id');

            PlatformComment::query()->updateOrCreate(
                ['author' => $comment['author'], 'target' => $comment['target']],
                [
                    ...$comment,
                    'course_id' => $courseId,
                ],
            );
        }
    }
}
