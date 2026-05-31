<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Рекомендации PlayNote</title>
</head>
<body style="margin:0;background:#f4f4f5;color:#111;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border:1px solid #111;">
                    <tr>
                        <td style="padding:34px;">
                            <p style="margin:0 0 24px;font-size:12px;letter-spacing:.24em;text-transform:uppercase;color:#666;">PlayNote</p>
                            <h1 style="margin:0 0 16px;font-size:32px;line-height:1.08;font-weight:400;">{{ $name }}, мы подобрали обучение</h1>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#444;">
                                Ваши ответы: {{ $instrument }}, уровень {{ $level }}, цель - {{ $goal }}.
                            </p>

                            @forelse ($courses as $course)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 14px;border:1px solid #111;">
                                    <tr>
                                        <td style="padding:18px 20px;">
                                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#666;">{{ $course->instrument }} · {{ $course->level }}</p>
                                            <h2 style="margin:0 0 10px;font-size:22px;line-height:1.2;">{{ $course->title }}</h2>
                                            <p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#555;">{{ $course->short_description }}</p>
                                            <a href="{{ url('/courses/'.$course->code) }}" style="color:#111;font-size:13px;letter-spacing:.12em;text-transform:uppercase;">Открыть курс</a>
                                        </td>
                                    </tr>
                                </table>
                            @empty
                                <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#555;">
                                    Сейчас нет точного совпадения, но мы сохранили заявку и поможем подобрать курс вручную.
                                </p>
                            @endforelse

                            <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#666;">
                                Вы получили это письмо после отправки формы подбора курса на сайте PlayNote.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
