<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\User;
use App\Models\UserVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserVideoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'videos' => UserVideo::query()
                ->with('user')
                ->where('status', 'опубликовано')
                ->latest()
                ->get()
                ->map(fn (UserVideo $video) => $video->toFrontend()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instrument' => ['required', 'string', 'max:128'],
            'image' => ['nullable', 'string', 'max:255'],
            'imageFile' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:102400'],
        ], [
            'video.required' => 'Выберите видео.',
            'video.file' => 'Выберите видео.',
            'video.mimetypes' => 'Формат не подходит.',
            'video.max' => 'Файл слишком большой.',
        ]);

        $videoPath = $request->file('video')->store('videos/community', 'public');
        $imagePath = $request->file('imageFile')?->store('images/community', 'public');
        unset($validated['imageFile']);

        $video = UserVideo::query()->create([
            ...$validated,
            'userId' => $request->session()->get('user_id'),
            'instrument_id' => Instrument::query()->where('name', $validated['instrument'])->value('id'),
            'image' => $imagePath ? '/storage/'.$imagePath : ($validated['image'] ?? '/images/course-theory.jpg'),
            'video' => '/storage/'.$videoPath,
            'status' => 'на модерации',
        ]);

        return response()->json([
            'video' => $video->load('user')->toFrontend(),
        ], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['опубликовано', 'на модерации', 'отклонено'])],
            'rejectionReason' => ['required_if:status,отклонено', 'nullable', 'string', 'max:1000'],
        ]);

        $video = UserVideo::query()->findOrFail($id);
        $rejectionReason = trim((string) ($validated['rejectionReason'] ?? ''));

        abort_if($validated['status'] === 'отклонено' && $rejectionReason === '', 422, 'Укажите причину отклонения.');

        $video->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'отклонено'
                ? $rejectionReason
                : null,
        ]);

        return response()->json([
            'video' => $video->load('user')->toFrontend(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $video = UserVideo::query()->findOrFail($id);
        $user = $this->user($request);

        abort_if(
            ! $user || ((int) $video->userId !== (int) $user->id && $user->role !== 'admin'),
            403,
            'Доступ запрещён.'
        );

        $this->deletePublicFile($video->video);
        $this->deletePublicFile($video->image);
        $video->delete();

        return response()->json(['success' => true]);
    }

    private function user(Request $request): ?User
    {
        $userId = $request->session()->get('user_id');

        return $userId ? User::query()->find($userId) : null;
    }

    private function deletePublicFile(?string $url): void
    {
        if (! $url || ! str_starts_with($url, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(substr($url, strlen('/storage/')));
    }
}
