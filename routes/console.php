<?php

use App\Models\Course;
use App\Models\Instrument;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserVideo;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('storage:prune-public {--force : Delete unused files instead of showing a dry run}', function () {
    $disk = Storage::disk('public');
    $referenced = collect();
    $keepFiles = ['.gitignore'];
    $force = (bool) $this->option('force');

    $remember = function (?string $value) use (&$referenced): void {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;

        if (str_starts_with($path, '/storage/')) {
            $referenced->push(ltrim(substr($path, strlen('/storage/')), '/'));
            return;
        }

        if (! str_starts_with($path, '/') && ! preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
            $referenced->push($path);
        }
    };

    Course::query()->select(['image', 'video'])->each(function (Course $course) use ($remember): void {
        $remember($course->image);
        $remember($course->video);
    });

    Lesson::query()->select(['image', 'video'])->each(function (Lesson $lesson) use ($remember): void {
        $remember($lesson->image);
        $remember($lesson->video);
    });

    Instrument::query()->select(['image'])->each(fn (Instrument $instrument) => $remember($instrument->image));

    UserVideo::query()->select(['image', 'video'])->each(function (UserVideo $video) use ($remember): void {
        $remember($video->image);
        $remember($video->video);
    });

    User::query()->select(['avatar', 'teacher_documents'])->each(function (User $user) use ($remember): void {
        $remember($user->avatar);

        foreach ($user->teacher_documents ?? [] as $document) {
            if (is_array($document)) {
                $remember($document['path'] ?? null);
            }
        }
    });

    $referenced = $referenced
        ->map(fn (string $path) => trim(str_replace('\\', '/', $path), '/'))
        ->filter()
        ->unique()
        ->values();

    $unused = collect($disk->allFiles())
        ->map(fn (string $path) => trim(str_replace('\\', '/', $path), '/'))
        ->reject(fn (string $path) => in_array(basename($path), $keepFiles, true))
        ->reject(fn (string $path) => $referenced->contains($path))
        ->values();

    if ($unused->isEmpty()) {
        $this->info('Unused public storage files not found.');
        return Command::SUCCESS;
    }

    $this->info(($force ? 'Deleting' : 'Dry run: would delete').' '.$unused->count().' unused file(s):');
    $unused->each(fn (string $path) => $this->line(' - '.$path));

    if ($force) {
        $disk->delete($unused->all());
        $this->info('Unused files deleted.');
    } else {
        $this->comment('Run with --force to delete these files.');
    }

    return Command::SUCCESS;
})->purpose('Delete files from storage/app/public that are not attached to site records');
