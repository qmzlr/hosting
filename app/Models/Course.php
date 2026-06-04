<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Course extends Model
{
    public const LEGACY_CODE_MAP = [
        '01' => 'osnovy-gitary',
        '02' => 'start-na-fortepiano',
        '03' => 'praktika-na-udarnyh',
        '04' => 'trenirovka-vokala',
        '05' => 'kurs-ukulele',
        '06' => 'muzykalnaya-teoriya',
    ];

    protected $fillable = [
        'code',
        'user_id',
        'status',
        'instrument_id',
        'title',
        'author',
        'category',
        'instrument',
        'image',
        'tagline',
        'short_description',
        'description',
        'features',
        'outcomes',
        'lessons',
        'lesson_count',
        'level',
        'duration',
        'duration_weeks',
        'progress',
        'video',
    ];

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'features' => 'array',
            'outcomes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Course $course): void {
            if ($course->instrument_id === null && $course->instrument && $course->instrument !== 'Любой инструмент') {
                $course->instrument_id = Instrument::query()->where('name', $course->instrument)->value('id');
            }
        });
    }

    public function lessonList(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function instrumentModel(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'instrument_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PlatformComment::class);
    }

    public static function resolveCode(string $code): string
    {
        return self::LEGACY_CODE_MAP[$code] ?? $code;
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'опубликовано')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('user_id')
                    ->orWhereHas('owner', fn (Builder $owner) => $owner
                        ->where('role', 'teacher')
                        ->where('teacher_status', 'одобрен')
                        ->where('is_banned', false));
            });
    }

    public function toFrontend(bool $withLessons = true, ?int $userId = null): array
    {
        $lessons = $withLessons ? $this->lessonList : collect();
        $lessonCount = $withLessons ? $lessons->count() : $this->lesson_count;
        $completedLessonIds = $this->completedLessonIds($lessons, $userId);
        $progress = 0;

        if ($userId !== null && $lessons->isNotEmpty()) {
            $progress = (int) round(($completedLessonIds->count() / $lessons->count()) * 100);
        }

        return [
            'id' => $this->code,
            'status' => $this->status,
            'owner' => $this->owner ? [
                'id' => (string) $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null,
            'title' => $this->title,
            'author' => $this->owner?->name ?: $this->owner?->email ?: $this->author,
            'category' => $this->category,
            'instrument' => $this->instrument,
            'img' => $this->image,
            'tagline' => $this->tagline,
            'shortDescription' => $this->short_description,
            'description' => $this->description,
            'features' => $this->features,
            'outcomes' => $this->outcomes,
            'lessons' => $this->formatLessonCount($lessonCount),
            'lessonCount' => $lessonCount,
            'level' => $this->level,
            'progress' => $progress,
            'video' => $this->video,
            'lessonList' => $withLessons
                ? $lessons->map(fn (Lesson $lesson) => $lesson->toFrontend($userId === null ? null : $completedLessonIds->contains($lesson->id)))->all()
                : [],
        ];
    }

    /**
     * @param Collection<int, Lesson> $lessons
     * @return Collection<int, int>
     */
    private function completedLessonIds(Collection $lessons, ?int $userId): Collection
    {
        if ($userId === null || $lessons->isEmpty()) {
            return collect();
        }

        return LessonProgress::query()
            ->where('userId', $userId)
            ->where('completed', true)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->pluck('lesson_id');
    }

    private function formatLessonCount(int $count): string
    {
        $remainder = $count % 100;
        $lastDigit = $count % 10;

        if ($remainder >= 11 && $remainder <= 14) {
            return "{$count} уроков";
        }

        if ($lastDigit === 1) {
            return "{$count} урок";
        }

        if ($lastDigit >= 2 && $lastDigit <= 4) {
            return "{$count} урока";
        }

        return "{$count} уроков";
    }
}
