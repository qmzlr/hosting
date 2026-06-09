<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\PlatformComment;
use App\Models\User;
use App\Models\UserVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformCommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $canModerate = $user && in_array($user->role, ['admin', 'moderator'], true);

        return response()->json([
            'comments' => PlatformComment::query()
                ->with(['course', 'lesson.course', 'video'])
                ->when(! $canModerate, fn ($query) => $query
                    ->where('status', 'одобрено')
                    ->where(function ($query): void {
                        $query
                            ->where('target_type', 'platform')
                            ->orWhereHas('course', fn ($course) => $course->publiclyVisible())
                            ->orWhereHas('lesson.course', fn ($course) => $course->publiclyVisible())
                            ->orWhereHas('video', fn ($video) => $video->where('status', 'опубликовано'));
                    }))
                ->latest()
                ->get()
                ->map(fn (PlatformComment $comment) => $comment->toFrontend()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_if(! $user, 403, 'Нужно войти в аккаунт.');

        $validated = $request->validate([
            'text' => ['required', 'string'],
            'targetType' => ['required', Rule::in(['course', 'lesson', 'video', 'platform'])],
            'targetCode' => ['required', 'string', 'max:128'],
        ]);

        $target = $this->resolveTarget($validated['targetType'], $validated['targetCode'], $user);

        $comment = PlatformComment::query()->create([
            'userId' => $user->id,
            'author' => $user->name ?: 'Ученик',
            'text' => $validated['text'],
            'target' => $target['label'],
            'target_type' => $validated['targetType'],
            'target_code' => $target['code'],
            'course_id' => $target['course_id'] ?? null,
            'lesson_id' => $target['lesson_id'] ?? null,
            'user_video_id' => $target['user_video_id'] ?? null,
            'status' => 'ожидает',
        ]);

        return response()->json([
            'comment' => $comment->toFrontend(),
        ], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['ожидает', 'одобрено', 'отклонено'])],
            'rejectionReason' => ['required_if:status,отклонено', 'nullable', 'string', 'max:1000'],
        ]);

        $comment = PlatformComment::query()->findOrFail($id);
        $rejectionReason = trim((string) ($validated['rejectionReason'] ?? ''));

        abort_if($validated['status'] === 'отклонено' && $rejectionReason === '', 422, 'Укажите причину отклонения.');

        $comment->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'отклонено'
                ? $rejectionReason
                : null,
        ]);

        return response()->json([
            'comment' => $comment->toFrontend(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        PlatformComment::query()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    private function user(Request $request): ?User
    {
        $userId = $request->session()->get('user_id');

        return $userId ? User::query()->find($userId) : null;
    }

    /**
     * @return array{label: string, code: string, course_id?: int, lesson_id?: int, user_video_id?: int}
     */
    private function resolveTarget(string $type, string $code, User $user): array
    {
        if ($type === 'course') {
            $course = Course::query()
                ->with('owner')
                ->where('code', Course::resolveCode($code))
                ->publiclyVisible()
                ->firstOrFail();
            $isEnrolled = CourseEnrollment::query()
                ->where('userId', $user->id)
                ->where('course_id', $course->id)
                ->exists();
            $lessonIds = $course->lessonList->pluck('id');
            $completedCount = $lessonIds->isEmpty()
                ? 0
                : LessonProgress::query()
                    ->where('userId', $user->id)
                    ->where('completed', true)
                    ->whereIn('lesson_id', $lessonIds)
                    ->distinct()
                    ->count('lesson_id');

            abort_if(! $isEnrolled, 403, 'Комментарий доступен после записи на курс.');
            abort_if($lessonIds->isEmpty() || $completedCount < $lessonIds->count(), 403, 'Комментарий доступен после прохождения всего курса.');

            return ['label' => $course->title, 'code' => $course->code, 'course_id' => $course->id];
        }

        if ($type === 'lesson') {
            $lesson = Lesson::query()
                ->with(['course.owner'])
                ->where('code', $code)
                ->whereHas('course', fn ($course) => $course->publiclyVisible())
                ->firstOrFail();
            $isCompleted = LessonProgress::query()
                ->where('userId', $user->id)
                ->where('lesson_id', $lesson->id)
                ->where('completed', true)
                ->exists();

            abort_if(! $isCompleted, 403, 'Комментарий доступен после прохождения урока.');

            return [
                'label' => ($lesson->course?->title ?? 'Курс').' · '.$lesson->title,
                'code' => $lesson->code,
                'lesson_id' => $lesson->id,
            ];
        }

        if ($type === 'video') {
            $video = UserVideo::query()
                ->where('status', 'опубликовано')
                ->findOrFail((int) $code);

            return ['label' => $video->title, 'code' => (string) $video->id, 'user_video_id' => $video->id];
        }

        return ['label' => 'PlayNote', 'code' => 'platform'];
    }
}
