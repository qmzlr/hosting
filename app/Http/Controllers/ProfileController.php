<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Support\EmailRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends Controller
{
    public function sendEmailChangeCode(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_if(! $user, 403, 'Нужно войти в аккаунт.');

        $validated = $request->validate([
            'email' => [
                'required',
                ...EmailRules::base(),
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], EmailRules::messages());

        $email = $this->normalizeEmail($validated['email']);

        if ($email === $this->normalizeEmail((string) $user->email)) {
            return response()->json([
                'success' => true,
                'message' => 'Email не изменился.',
            ]);
        }

        $this->sendCode(
            $email,
            'email_change',
            'Подтверждение нового email',
            'Введите этот код в окне редактирования профиля, чтобы подтвердить новый email.'
        );

        return response()->json([
            'success' => true,
            'message' => 'Код подтверждения отправлен на новый email.',
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_if(! $user, 403, 'Нужно войти в аккаунт.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                ...EmailRules::base(),
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'avatar' => ['nullable', 'string', 'max:1024'],
            'level' => ['nullable', 'string', 'max:64'],
            'emailVerificationCode' => ['nullable', 'string', 'size:6'],
            'currentPassword' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'instrumentIds' => ['sometimes', 'array'],
            'instrumentIds.*' => ['string', 'exists:instruments,slug'],
        ], EmailRules::messages());
        $requiresEmailChange = (bool) $user->must_change_email;

        if ($requiresEmailChange && empty($validated['instrumentIds'])) {
            throw ValidationException::withMessages([
                'instrumentIds' => 'Выберите хотя бы один интересующий инструмент.',
            ]);
        }

        $instrumentIds = $validated['instrumentIds'] ?? null;
        unset($validated['instrumentIds']);
        $emailVerificationCode = $validated['emailVerificationCode'] ?? null;
        unset($validated['emailVerificationCode']);
        $currentPassword = $validated['currentPassword'] ?? null;
        unset($validated['currentPassword']);

        if ($requiresEmailChange) {
            $validated['name'] = $user->name;
            unset($validated['avatar'], $validated['level'], $validated['password']);
        }

        if (array_key_exists('email', $validated)) {
            $validated['email'] = $this->normalizeEmail((string) $validated['email']);
            $emailChanged = $validated['email'] !== $this->normalizeEmail((string) $user->email);

            if ($emailChanged) {
                if (! $emailVerificationCode) {
                    throw ValidationException::withMessages([
                        'emailVerificationCode' => 'Введите код подтверждения для нового email.',
                    ]);
                }

                $this->verifyCode($validated['email'], 'email_change', $emailVerificationCode);
            }

            if ($requiresEmailChange && ! $emailChanged) {
                throw ValidationException::withMessages([
                    'email' => 'Смените email, выданный администратором.',
                ]);
            }
        } elseif ($requiresEmailChange) {
            throw ValidationException::withMessages([
                'email' => 'Смените email, выданный администратором.',
            ]);
        }

        if (! empty($validated['password'])) {
            if (! $currentPassword || ! $user->password || ! Hash::check($currentPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'currentPassword' => 'Введите текущий пароль правильно.',
                ]);
            }

            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($instrumentIds !== null) {
            $firstInstrument = Instrument::query()
                ->whereIn('slug', $instrumentIds)
                ->orderByRaw($this->instrumentOrderSql($instrumentIds))
                ->first();

            $validated['instrument'] = $firstInstrument?->name;
        }

        if ($requiresEmailChange) {
            $validated['must_change_email'] = false;
        }

        $user->update($validated);

        if ($instrumentIds !== null) {
            $instrumentPrimaryKeys = Instrument::query()
                ->whereIn('slug', $instrumentIds)
                ->pluck('id')
                ->all();

            $user->instruments()->sync($instrumentPrimaryKeys);
        }

        return response()->json([
            'user' => $user->fresh()?->load('instruments'),
            'instruments' => $user->fresh()?->instruments->map(fn (Instrument $instrument) => $instrument->toFrontend())->values(),
        ]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_if(! $user, 403, 'Нужно войти в аккаунт.');

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], [
            'avatar.required' => 'Выберите файл.',
            'avatar.image' => 'Нужно изображение.',
            'avatar.mimes' => 'Формат не подходит.',
            'avatar.max' => 'Файл слишком большой.',
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $avatar = '/storage/'.$path;

        $user->update(['avatar' => $avatar]);

        return response()->json([
            'avatar' => $avatar,
            'user' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_if(! $user, 403, 'Нужно войти в аккаунт.');

        $request->session()->forget('user_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        return response()->json(['success' => true]);
    }

    private function user(Request $request): ?User
    {
        $userId = $request->session()->get('user_id');

        return $userId ? User::query()->find($userId) : null;
    }

    private function sendCode(string $email, string $purpose, string $title, string $intro): void
    {
        $code = (string) random_int(100000, 999999);
        $ttlMinutes = 15;

        EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->delete();

        EmailVerificationCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        try {
            Mail::send('emails.auth-code', [
                'title' => $title,
                'intro' => $intro,
                'code' => $code,
                'ttlMinutes' => $ttlMinutes,
            ], function ($message) use ($email, $title) {
                $message->to($email)->subject('PlayNote: '.$title);
            });
        } catch (Throwable $e) {
            report($e);

            EmailVerificationCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->delete();

            throw ValidationException::withMessages([
                'email' => 'Не удалось отправить письмо. Проверьте SMTP-настройки на хостинге.',
            ]);
        }
    }

    private function verifyCode(string $email, string $purpose, string $code): void
    {
        $verification = EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $verification || $verification->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'emailVerificationCode' => 'Код истёк или не найден. Запросите новый код.',
            ]);
        }

        if ($verification->attempts >= 5) {
            throw ValidationException::withMessages([
                'emailVerificationCode' => 'Слишком много попыток. Запросите новый код.',
            ]);
        }

        if (! Hash::check($code, $verification->code_hash)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages([
                'emailVerificationCode' => 'Неверный код подтверждения.',
            ]);
        }

        $verification->update(['consumed_at' => now()]);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Preserve the first selected instrument as the legacy primary instrument.
     */
    private function instrumentOrderSql(array $instrumentIds): string
    {
        $cases = collect($instrumentIds)
            ->values()
            ->map(fn (string $slug, int $index) => "WHEN '".str_replace("'", "''", $slug)."' THEN {$index}")
            ->implode(' ');

        return "CASE slug {$cases} ELSE 999 END";
    }
}
