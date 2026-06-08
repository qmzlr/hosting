<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ModerationController extends Controller
{
    public function updateTeacherStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['ожидает', 'одобрен', 'отклонён'])],
            'rejectionReason' => ['required_if:status,отклонён', 'nullable', 'string', 'max:1000'],
        ]);

        $teacher = User::query()
            ->with('instruments')
            ->where('role', 'teacher')
            ->findOrFail($id);
        $rejectionReason = trim((string) ($validated['rejectionReason'] ?? ''));

        abort_if($validated['status'] === 'отклонён' && $rejectionReason === '', 422, 'Укажите причину отклонения.');

        $teacher->update([
            'teacher_status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'отклонён'
                ? $rejectionReason
                : null,
        ]);

        return response()->json([
            'teacher' => $this->teacherPayload($teacher->fresh('instruments')),
        ]);
    }

    public function updateCourseStatus(Request $request, string $code): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['на модерации', 'опубликовано', 'отклонено'])],
            'rejectionReason' => ['required_if:status,отклонено', 'nullable', 'string', 'max:1000'],
        ]);

        $course = Course::query()
            ->with(['lessonList', 'owner'])
            ->where('code', Course::resolveCode($code))
            ->firstOrFail();
        $rejectionReason = trim((string) ($validated['rejectionReason'] ?? ''));

        abort_if($validated['status'] === 'отклонено' && $rejectionReason === '', 422, 'Укажите причину отклонения.');

        $course->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'отклонено'
                ? $rejectionReason
                : null,
        ]);

        return response()->json([
            'course' => $course->fresh(['lessonList', 'owner'])->toFrontend(),
        ]);
    }

    private function teacherPayload(User $teacher): array
    {
        return [
            'id' => (string) $teacher->id,
            'name' => $teacher->name,
            'email' => $teacher->email,
            'status' => $teacher->teacher_status ?? 'ожидает',
            'rejectionReason' => $teacher->rejection_reason,
            'instrument' => $teacher->instrument,
            'instrumentIds' => $teacher->instruments->pluck('slug')->values()->all(),
            'instruments' => $teacher->instruments->pluck('name')->values()->all(),
            'documents' => collect($teacher->teacher_documents ?? [])
                ->map(fn (array $document) => [
                    'name' => $document['name'] ?? basename($document['path'] ?? ''),
                    'url' => isset($document['path']) ? Storage::disk('public')->url($document['path']) : '#',
                    'mime' => $document['mime'] ?? null,
                    'size' => (int) ($document['size'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }
}
