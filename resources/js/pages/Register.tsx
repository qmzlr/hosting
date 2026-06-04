import { router } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import { AppShell } from '@/components/AppShell'
import { MediaAttachmentPreview } from '@/components/MediaAttachmentPreview'
import type { Instrument } from '@/data/courses'
import { postFormData, postJson } from '@/lib/http'
import { documentAccept, validateDocumentFile } from '@/lib/uploads'

const urlPattern = /(?:https?:\/\/|www\.|[a-z0-9-]+\.[a-z]{2,})(?:\S*)/i
const namePattern = /^[\p{L}\s-]+$/u

type RegisterErrors = {
  name?: string
  email?: string
  password?: string
  passwordConfirmation?: string
  instruments?: string
  agreement?: string
  emailVerificationCode?: string
}

export default function Register({ instruments }: { instruments: Instrument[] }) {
  const [message, setMessage] = useState('')
  const [messageTone, setMessageTone] = useState<'error' | 'info' | 'success'>('info')
  const [accountType, setAccountType] = useState<'student' | 'teacher'>('student')
  const [agreed, setAgreed] = useState(false)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [emailVerificationCode, setEmailVerificationCode] = useState('')
  const [isCodeSent, setIsCodeSent] = useState(false)
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [teacherDocuments, setTeacherDocuments] = useState<File[]>([])
  const [selectedIds, setSelectedIds] = useState(() => new Set<string>())
  const [level, setLevel] = useState('Начинающий')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [touched, setTouched] = useState<Record<keyof RegisterErrors, boolean>>({
    name: false,
    email: false,
    password: false,
    passwordConfirmation: false,
    instruments: false,
    agreement: false,
    emailVerificationCode: false,
  })

  const errors = useMemo<RegisterErrors>(() => {
    const nextErrors: RegisterErrors = {}
    const trimmedName = name.trim()
    const trimmedEmail = email.trim()

    if (!trimmedName) {
      nextErrors.name = 'Введите имя.'
    } else if (!namePattern.test(trimmedName)) {
      nextErrors.name = 'Используйте только буквы, пробел и дефис.'
    } else if (urlPattern.test(trimmedName)) {
      nextErrors.name = 'Ссылки в имени не допускаются.'
    }

    if (!trimmedEmail) {
      nextErrors.email = 'Введите email.'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
      nextErrors.email = 'Введите корректный email.'
    }

    if (!password) {
      nextErrors.password = 'Введите пароль.'
    } else if (password.length < 6) {
      nextErrors.password = 'Пароль должен быть не короче 6 символов.'
    }

    if (!passwordConfirmation) {
      nextErrors.passwordConfirmation = 'Повторите пароль.'
    } else if (password && password !== passwordConfirmation) {
      nextErrors.passwordConfirmation = 'Пароли не совпадают.'
    }

    if (selectedIds.size === 0) {
      nextErrors.instruments = 'Выберите хотя бы один инструмент.'
    }

    if (!agreed) {
      nextErrors.agreement = 'Подтвердите согласие на обработку персональных данных.'
    }

    if (isCodeSent && emailVerificationCode.length !== 6) {
      nextErrors.emailVerificationCode = 'Введите 6 цифр из письма.'
    }

    return nextErrors
  }, [agreed, email, emailVerificationCode, isCodeSent, name, password, passwordConfirmation, selectedIds])

  const hasVisibleError = (field: keyof RegisterErrors) => touched[field] && errors[field]
  const markTouched = (field: keyof RegisterErrors) => {
    setTouched((current) => ({ ...current, [field]: true }))
  }
  const markAllTouched = () => {
    setTouched({
      name: true,
      email: true,
      password: true,
      passwordConfirmation: true,
      instruments: true,
      agreement: true,
      emailVerificationCode: true,
    })
  }
  const isCurrentStepValid = isCodeSent
    ? !errors.emailVerificationCode
    : !errors.name && !errors.email && !errors.password && !errors.passwordConfirmation && !errors.instruments && !errors.agreement

  const addTeacherDocuments = (files: FileList | null) => {
    const incoming = Array.from(files ?? [])
    if (incoming.length === 0) return

    const validFiles: File[] = []
    for (const file of incoming) {
      const validationMessage = validateDocumentFile(file)
      if (validationMessage) {
        setMessageTone('error')
        setMessage(`${file.name}: ${validationMessage}`)
        return
      }
      validFiles.push(file)
    }

    setTeacherDocuments((current) => {
      const next = [...current, ...validFiles].slice(0, 8)
      if (current.length + validFiles.length > 8) {
        setMessageTone('error')
        setMessage('Можно приложить до 8 файлов.')
      } else {
        setMessage('')
      }
      return next
    })
  }

  const removeTeacherDocument = (index: number) => {
    setTeacherDocuments((current) => current.filter((_, currentIndex) => currentIndex !== index))
  }

  const toggleInstrument = (id: string) => {
    markTouched('instruments')
    setSelectedIds((current) => {
      const next = new Set(current)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }

  const updateName = (value: string) => {
    markTouched('name')
    setName(value.replace(/[^\p{L}\s-]/gu, ''))
  }

  return (
    <AppShell>
      <section className="auth-page">
        <div className="auth-visual">
          <img src="/images/work-05.jpg" alt="Instrument" />
          <div>
            <p className="pn-kicker">New student</p>
            <h1>Создайте маршрут обучения</h1>
          </div>
        </div>
        <form
          className="auth-form"
          onSubmit={async (e) => {
            e.preventDefault()
            markAllTouched()
            if (!isCurrentStepValid) {
              setMessageTone('error')
              setMessage(isCodeSent ? 'Введите код подтверждения из письма.' : 'Проверьте заполненные поля.')
              return
            }

            setIsSubmitting(true)
            setMessage('')

            try {
              if (!isCodeSent) {
                await postJson('/register/email-code', { email: email.trim() })
                setIsCodeSent(true)
                setMessageTone('info')
                setMessage('Код подтверждения отправлен на почту. Введите его, чтобы завершить регистрацию.')
                return
              }

              const body = new FormData()
              body.append('name', name.trim())
              body.append('email', email.trim())
              body.append('emailVerificationCode', emailVerificationCode)
              body.append('password', password)
              body.append('password_confirmation', passwordConfirmation)
              body.append('accountType', accountType)
              body.append('instrument', instruments.find((item) => selectedIds.has(item.id))?.name ?? '')
              Array.from(selectedIds).forEach((id) => body.append('instrumentIds[]', id))
              if (accountType === 'student') {
                body.append('level', level)
              }
              if (accountType === 'teacher') {
                teacherDocuments.forEach((file) => body.append('teacherDocuments[]', file))
              }

              await postFormData('/register', body)
              router.visit(accountType === 'teacher' ? '/teacher' : '/profile')
            } catch (error) {
              setMessageTone('error')
              setMessage(error instanceof Error ? error.message : 'Не удалось создать аккаунт.')
            } finally {
              setIsSubmitting(false)
            }
          }}
        >
          <h2>Создание аккаунта</h2>
          {isCodeSent ? (
            <div className="auth-code-step">
              <div>
                <div className="pn-meta">Подтверждение email</div>
                <p className="pn-text">Код отправлен на {email}. Введите его, чтобы завершить регистрацию.</p>
              </div>
              <input
                className="pn-input auth-code-input"
                inputMode="numeric"
                maxLength={6}
                pattern="[0-9]{6}"
                value={emailVerificationCode}
                onChange={(e) => {
                  markTouched('emailVerificationCode')
                  setEmailVerificationCode(e.target.value.replace(/\D/g, '').slice(0, 6))
                }}
                onBlur={() => markTouched('emailVerificationCode')}
                placeholder="Код из письма"
                required
                autoFocus
                aria-invalid={Boolean(hasVisibleError('emailVerificationCode'))}
                aria-describedby={hasVisibleError('emailVerificationCode') ? 'register-code-error' : undefined}
              />
              {hasVisibleError('emailVerificationCode') && <p className="field-error" id="register-code-error">{errors.emailVerificationCode}</p>}
            </div>
          ) : (
            <>
              <div className="account-type-toggle" aria-label="Тип аккаунта">
                <button
                  className={accountType === 'student' ? 'is-active' : ''}
                  type="button"
                  onClick={() => setAccountType('student')}
                >
                  Ученик
                </button>
                <button
                  className={accountType === 'teacher' ? 'is-active' : ''}
                  type="button"
                  onClick={() => setAccountType('teacher')}
                >
                  Учитель
                </button>
              </div>
              <input
                className="pn-input"
                value={name}
                onChange={(e) => {
                  updateName(e.target.value)
                }}
                onBlur={() => markTouched('name')}
                placeholder="Имя"
                required
                aria-invalid={Boolean(hasVisibleError('name'))}
                aria-describedby={hasVisibleError('name') ? 'register-name-error' : undefined}
              />
              {hasVisibleError('name') && <p className="field-error" id="register-name-error">{errors.name}</p>}
              <input
                className="pn-input"
                type="email"
                value={email}
                onChange={(e) => {
                  markTouched('email')
                  setEmail(e.target.value)
                  setEmailVerificationCode('')
                }}
                onBlur={() => markTouched('email')}
                placeholder="Email"
                required
                aria-invalid={Boolean(hasVisibleError('email'))}
                aria-describedby={hasVisibleError('email') ? 'register-email-error' : undefined}
              />
              {hasVisibleError('email') && <p className="field-error" id="register-email-error">{errors.email}</p>}
              <input
                className="pn-input"
                type="password"
                value={password}
                onChange={(e) => {
                  markTouched('password')
                  setPassword(e.target.value)
                }}
                onBlur={() => markTouched('password')}
                placeholder="Пароль"
                required
                aria-invalid={Boolean(hasVisibleError('password'))}
                aria-describedby={hasVisibleError('password') ? 'register-password-error' : undefined}
              />
              {hasVisibleError('password') && <p className="field-error" id="register-password-error">{errors.password}</p>}
              <input
                className="pn-input"
                type="password"
                value={passwordConfirmation}
                onChange={(e) => {
                  markTouched('passwordConfirmation')
                  setPasswordConfirmation(e.target.value)
                }}
                onBlur={() => markTouched('passwordConfirmation')}
                placeholder="Подтверждение пароля"
                required
                aria-invalid={Boolean(hasVisibleError('passwordConfirmation'))}
                aria-describedby={hasVisibleError('passwordConfirmation') ? 'register-password-confirmation-error' : undefined}
              />
              {hasVisibleError('passwordConfirmation') && <p className="field-error" id="register-password-confirmation-error">{errors.passwordConfirmation}</p>}
              <div className="pn-meta">{accountType === 'teacher' ? 'Инструменты преподавания' : 'Интересующие инструменты'}</div>
              <div
                className="dashboard-chip-list profile-instrument-picker auth-instrument-picker"
                aria-label="Инструменты"
                aria-invalid={Boolean(hasVisibleError('instruments'))}
                aria-describedby={hasVisibleError('instruments') ? 'register-instruments-error' : undefined}
              >
                {instruments.map((instrument) => (
                  <label className={`dashboard-chip ${selectedIds.has(instrument.id) ? 'is-selected' : ''}`} key={instrument.id}>
                    <input
                      type="checkbox"
                      checked={selectedIds.has(instrument.id)}
                      onChange={() => toggleInstrument(instrument.id)}
                    />
                    {instrument.name}
                  </label>
                ))}
              </div>
              {hasVisibleError('instruments') && <p className="field-error" id="register-instruments-error">{errors.instruments}</p>}
              {accountType === 'student' ? (
                <select className="pn-select" value={level} onChange={(e) => setLevel(e.target.value)}>
                  <option>Начинающий</option>
                  <option>Базовый</option>
                  <option>Средний</option>
                </select>
              ) : (
                <>
                  <label className="teacher-documents-field">
                    <span>Сертификаты, грамоты, дипломы</span>
                    <input
                      type="file"
                      multiple
                      accept={documentAccept}
                      onChange={(e) => {
                        addTeacherDocuments(e.target.files)
                        e.target.value = ''
                      }}
                    />
                    <em>
                      Можно приложить до 8 файлов: PDF, изображения, DOC или DOCX. Модератор увидит их вместе с заявкой.
                    </em>
                    {teacherDocuments.length > 0 && (
                      <strong>{teacherDocuments.length} файл(а) выбрано</strong>
                    )}
                    {teacherDocuments.length > 0 && (
                      <div className="teacher-documents-list">
                        {teacherDocuments.map((file, index) => (
                          <div key={`${file.name}-${file.lastModified}-${index}`}>
                            <span>{file.name}</span>
                            <button type="button" onClick={() => removeTeacherDocument(index)}>Убрать</button>
                          </div>
                        ))}
                      </div>
                    )}
                    {teacherDocuments.length > 0 && (
                      <MediaAttachmentPreview values={teacherDocuments} kind="file" onRemoveValue={removeTeacherDocument} />
                    )}
                  </label>
                  <p className="teacher-register-note">После регистрации модератор проверит заявку. Курсы можно будет создавать после одобрения.</p>
                </>
              )}
              <label className="privacy-consent">
                <input
                  type="checkbox"
                  checked={agreed}
                  required
                  onChange={(e) => {
                    markTouched('agreement')
                    setAgreed(e.target.checked)
                  }}
                  onBlur={() => markTouched('agreement')}
                  aria-invalid={Boolean(hasVisibleError('agreement'))}
                  aria-describedby={hasVisibleError('agreement') ? 'register-agreement-error' : undefined}
                />
                <span>
                  Я согласен на обработку персональных данных и ознакомлен с{' '}
                  <button type="button" onClick={() => router.visit('/privacy')}>политикой конфиденциальности</button>.
                </span>
              </label>
              {hasVisibleError('agreement') && <p className="field-error" id="register-agreement-error">{errors.agreement}</p>}
            </>
          )}
          <button className="pn-button is-dark" disabled={isSubmitting || !isCurrentStepValid}>
            {isSubmitting ? (isCodeSent ? 'Создаем...' : 'Отправляем...') : isCodeSent ? 'Создать аккаунт' : 'Получить код'}
          </button>
          {isCodeSent && (
            <div className="auth-code-actions">
              <button
                type="button"
                className="auth-link"
                disabled={isSubmitting}
                onClick={async () => {
                  setIsSubmitting(true)
                  setMessage('')
                  try {
                    await postJson('/register/email-code', { email })
                    setMessageTone('success')
                    setMessage('Новый код подтверждения отправлен на почту.')
                  } catch (error) {
                    setMessageTone('error')
                    setMessage(error instanceof Error ? error.message : 'Не удалось отправить код.')
                  } finally {
                    setIsSubmitting(false)
                  }
                }}
              >
                Отправить код еще раз
              </button>
              <button
                type="button"
                className="auth-link"
                disabled={isSubmitting}
                onClick={() => {
                  setIsCodeSent(false)
                  setEmailVerificationCode('')
                  setMessage('')
                }}
              >
                Изменить данные
              </button>
            </div>
          )}
          <button type="button" className="auth-link" onClick={() => router.visit('/login')}>Уже есть аккаунт?</button>
          {message && <p className={`pn-message is-${messageTone}`}>{message}</p>}
        </form>
      </section>
    </AppShell>
  )
}
