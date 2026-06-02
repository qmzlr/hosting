<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CourseRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:320'],
            'instrument' => ['required', 'string', 'max:128'],
            'level' => ['required', 'string', 'max:64'],
            'goal' => ['required', 'string', 'max:255'],
            'privacyConsent' => ['accepted'],
        ]);

        $courseRequest = CourseRequest::query()->create([
            ...collect($validated)->except('privacyConsent')->all(),
            'userId' => $request->session()->get('user_id'),
            'instrument_id' => Instrument::query()->where('name', $validated['instrument'])->value('id'),
        ]);

        $courses = $this->matchingCourses($courseRequest);

        Mail::send('emails.course-recommendations', [
            'name' => $courseRequest->name,
            'instrument' => $courseRequest->instrument,
            'level' => $courseRequest->level,
            'goal' => $courseRequest->goal,
            'courses' => $courses,
        ], function ($message) use ($courseRequest) {
            $message
                ->to($courseRequest->email, $courseRequest->name)
                ->subject('PlayNote: курсы, которые вам подойдут');
        });

        return response()->json([
            'id' => $courseRequest->id,
            'success' => true,
        ]);
    }

    private function matchingCourses(CourseRequest $request)
    {
        $normalizedLevel = $request->level === 'Новичок' ? 'Начинающий' : $request->level;

        return Course::query()
            ->with('owner')
            ->publiclyVisible()
            ->get()
            ->sortByDesc(function (Course $course) use ($request, $normalizedLevel): int {
                $score = 0;

                if ($course->instrument === $request->instrument || $course->instrument_id === $request->instrument_id) {
                    $score += 4;
                }

                if ($course->instrument === 'Любой инструмент') {
                    $score += 2;
                }

                if ($course->level === $normalizedLevel) {
                    $score += 2;
                }

                if ($normalizedLevel === 'Начинающий' && $course->level === 'Начинающий') {
                    $score += 1;
                }

                return $score;
            })
            ->filter(function (Course $course) use ($request, $normalizedLevel): bool {
                return $course->instrument === $request->instrument
                    || $course->instrument_id === $request->instrument_id
                    || $course->instrument === 'Любой инструмент'
                    || $course->level === $normalizedLevel;
            })
            ->take(4)
            ->values();
    }
}
