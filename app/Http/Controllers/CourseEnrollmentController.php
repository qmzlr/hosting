<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseEnrollmentController extends Controller
{
    public function store(Request $request, string $courseCode): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        abort_if(! $userId, 403, 'Нужно войти в аккаунт.');

        $course = Course::query()
            ->with(['lessonList', 'owner'])
            ->where('code', Course::resolveCode($courseCode))
            ->publiclyVisible()
            ->firstOrFail();

        CourseEnrollment::query()->firstOrCreate(
            [
                'userId' => $userId,
                'course_id' => $course->id,
            ],
            [
                'startedAt' => now(),
            ],
        );

        $completedLessonIds = LessonProgress::query()
            ->where('userId', $userId)
            ->where('completed', true)
            ->whereIn('lesson_id', $course->lessonList->pluck('id'))
            ->pluck('lesson_id')
            ->all();
        $targetLesson = $course->lessonList->first(fn ($lesson) => ! in_array($lesson->id, $completedLessonIds, true))
            ?? $course->lessonList->last();

        return response()->json([
            'course' => $course->toFrontend(true, (int) $userId),
            'lessonUrl' => $targetLesson ? route('lessons.show', [$course->code, $targetLesson->code], false) : null,
        ], 201);
    }
}
