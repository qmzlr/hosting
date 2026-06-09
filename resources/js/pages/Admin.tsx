import { useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import { AppShell, PageHero, SectionTitle } from '@/components/AppShell'
import { MediaAttachmentPreview } from '@/components/MediaAttachmentPreview'
import { initialUploadProgress, UploadProgress } from '@/components/UploadProgress'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import type { AdminUser, Course, Instrument } from '@/data/courses'
import { deleteJson, patchJson, postJson, putJson, uploadFormData, type UploadProgressState } from '@/lib/http'
import { imageAccept, validateImageFile } from '@/lib/uploads'
import { isValidEmail } from '@/lib/validation'
import { toast } from 'sonner'

type Tab = 'Курсы' | 'Пользователи' | 'Инструменты'
type Workspace = 'admin' | 'teacher'

const tabs: Tab[] = ['Курсы', 'Пользователи', 'Инструменты']
const roles: AdminUser['role'][] = ['user', 'teacher', 'moderator', 'admin']
const teacherStatuses: NonNullable<AdminUser['teacherStatus']>[] = ['ожидает', 'одобрен', 'отклонён']
const levels = ['Начинающий', 'Базовый', 'Средний']
const adminPageSize = 8
const banReasons = [
  { value: '', label: 'Не забанен' },
  { value: 'spam', label: 'Спам' },
  { value: 'abuse', label: 'Оскорбления' },
  { value: 'rules', label: 'Нарушение правил' },
  { value: 'security', label: 'Безопасность' },
  { value: 'other', label: 'Другое' },
]
const fixedBanReasonValues = banReasons.map((reason) => reason.value)

export default function Admin({
  adminStats,
  courses,
  instruments,
  isSuperAdmin = false,
  users,
  workspace = 'admin',
}: {
  adminStats: [string, string][]
  courses: Course[]
  instruments: Instrument[]
  isSuperAdmin?: boolean
  users: AdminUser[]
  workspace?: Workspace
}) {
  const [active, setActive] = useState<Tab | null>(() => tabFromLocation())
  const [query, setQuery] = useState('')
  const [roleFilter, setRoleFilter] = useState<AdminUser['role'] | 'all'>('all')
  const [courseStatusFilter, setCourseStatusFilter] = useState('all')
  const [courseInstrumentFilter, setCourseInstrumentFilter] = useState('all')
  const [courseLevelFilter, setCourseLevelFilter] = useState('all')
  const [courseItems, setCourseItems] = useState(courses)
  const [userItems, setUserItems] = useState(users)
  const [instrumentItems, setInstrumentItems] = useState(instruments)
  const availableRoles = isSuperAdmin ? roles : roles.filter((role) => role !== 'admin')
  const liveStats = useMemo(() => {
    if (workspace === 'teacher') {
      return [
        ['Мои курсы', String(courseItems.length)],
        ['На модерации', String(courseItems.filter((course) => course.status === 'на модерации').length)],
        ['Опубликовано', String(courseItems.filter((course) => course.status === 'опубликовано').length)],
      ] as [string, string][]
    }

    return [
      ['Пользователи', String(userItems.length)],
      ['Курсы', String(courseItems.length)],
      ['Инструменты', String(instrumentItems.length)],
    ] as [string, string][]
  }, [courseItems, instrumentItems, userItems, workspace])

  const availableTabs = workspace === 'teacher' ? (['Курсы'] as Tab[]) : tabs
  const resolvedActive = active && availableTabs.includes(active) ? active : null
  const title = workspace === 'teacher' ? 'Кабинет учителя' : 'Панель администратора'
  const text = workspace === 'teacher'
    ? 'Создавайте курсы и отправляйте их на модерацию перед публикацией.'
    : 'Управление курсами, пользователями и инструментами PlayNote.'

  return (
    <AppShell>
      <PageHero eyebrow={workspace === 'teacher' ? 'Teacher studio' : 'System control'} title={title} text={text} image="/images/work-04.jpg" />
      <section className="pn-section admin-section">
        <div className="pn-container">
          <SectionTitle title="Статистика" aside="Система" />
          <div className="admin-stats">
            {(liveStats.length > 0 ? liveStats : adminStats).map(([label, value]) => (
              <article className="pn-card pn-card-body admin-stat-card" key={label}>
                <div className="pn-meta">{label}</div>
                <strong>{value}</strong>
                <span>активно</span>
              </article>
            ))}
          </div>
          {!resolvedActive ? (
            <>
              <div className="admin-choice-grid">
                {availableTabs.map((tab) => (
                  <button className="pn-card pn-card-body admin-choice-card" key={tab} onClick={() => {
                    setActive(tab)
                    setQuery('')
                    setRoleFilter('all')
                    setCourseStatusFilter('all')
                    setCourseInstrumentFilter('all')
                    setCourseLevelFilter('all')
                  }}>
                    <div className="pn-meta">Раздел</div>
                    <strong>{tab}</strong>
                    <span>{sectionHint(tab)}</span>
                  </button>
                ))}
              </div>
            </>
          ) : (
            <>
              <div className={`admin-workbench ${resolvedActive === 'Курсы' ? 'has-course-filters' : ''} ${workspace === 'admin' && resolvedActive === 'Пользователи' ? 'has-role-filter' : ''}`}>
                <button className="pn-button" onClick={() => {
                  setActive(null)
                  setQuery('')
                  setRoleFilter('all')
                  setCourseStatusFilter('all')
                  setCourseInstrumentFilter('all')
                  setCourseLevelFilter('all')
                }}>Назад к разделам</button>
                {resolvedActive === 'Курсы' && (
                  <div className="admin-course-filters">
                    <label className="catalog-filter">
                      <span>Поиск</span>
                      <input className="pn-input" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Поиск: курсы" />
                    </label>
                    <AdminFilter label="Статус" value={courseStatusFilter} onChange={setCourseStatusFilter} options={courseFilterOptions(courseItems.map((course) => course.status ?? 'опубликовано'))} allLabel="Все статусы" />
                    <AdminFilter label="Инструмент" value={courseInstrumentFilter} onChange={setCourseInstrumentFilter} options={courseFilterOptions(courseItems.map((course) => course.instrument))} allLabel="Все инструменты" />
                    <AdminFilter label="Уровень" value={courseLevelFilter} onChange={setCourseLevelFilter} options={courseFilterOptions(courseItems.map((course) => course.level))} allLabel="Все уровни" />
                  </div>
                )}
                {resolvedActive !== 'Курсы' && (
                  <input className="pn-input" value={query} onChange={(event) => setQuery(event.target.value)} placeholder={`Поиск: ${resolvedActive.toLowerCase()}`} />
                )}
                {workspace === 'admin' && resolvedActive === 'Пользователи' && (
                  <label className="catalog-filter admin-role-filter">
                    <span>Роль</span>
                    <select className="pn-select" value={roleFilter} onChange={(event) => setRoleFilter(event.target.value as AdminUser['role'] | 'all')}>
                      <option value="all">Все роли</option>
                      {availableRoles.map((role) => <option key={role} value={role}>{roleLabel(role)}</option>)}
                    </select>
                  </label>
                )}
              </div>
              {resolvedActive === 'Курсы' && <CoursesPanel courses={courseItems} filters={{ status: courseStatusFilter, instrument: courseInstrumentFilter, level: courseLevelFilter }} query={query} workspace={workspace} onChange={setCourseItems} />}
              {workspace === 'admin' && resolvedActive === 'Пользователи' && <UsersPanel availableRoles={availableRoles} instruments={instrumentItems} query={query} roleFilter={roleFilter} users={userItems} onChange={setUserItems} />}
              {workspace === 'admin' && resolvedActive === 'Инструменты' && <InstrumentsPanel instruments={instrumentItems} query={query} onChange={setInstrumentItems} />}
            </>
          )}
        </div>
      </section>
    </AppShell>
  )
}

function CoursesPanel({
  courses,
  filters,
  query,
  workspace,
  onChange,
}: {
  courses: Course[]
  filters: { status: string; instrument: string; level: string }
  query: string
  workspace: Workspace
  onChange: (courses: Course[]) => void
}) {
  const rows = filterRows(courses, query, (course) => `${course.id} ${course.title} ${course.instrument} ${course.level} ${course.status ?? ''}`)
    .filter((course) => filters.status === 'all' || (course.status ?? 'опубликовано') === filters.status)
    .filter((course) => filters.instrument === 'all' || course.instrument === filters.instrument)
    .filter((course) => filters.level === 'all' || course.level === filters.level)
  const page = usePagedRows(rows)
  const basePath = workspace === 'teacher' ? '/teacher/courses' : '/admin/courses'

  const remove = async (course: Course) => {
    if (!window.confirm(`Удалить курс "${course.title}"?`)) return
    try {
      await deleteJson('/api/courses/' + course.id)
      onChange(courses.filter((item) => item.id !== course.id))
      toast.success('Курс удалён.')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  return (
    <AdminPanel title="Курсы" aside={`${rows.length} записей`} actionLabel="Создать курс" onAction={() => router.visit(`${basePath}/new`)}>
      <div className="admin-table">
        {page.rows.map((course) => (
          <article className="admin-row" key={course.id}>
            <span><strong>{course.title}</strong><em>{course.id} · {course.instrument} · {course.level} · {course.status ?? 'опубликовано'}</em></span>
            <button className="pn-button" onClick={() => router.visit(`${basePath}/${course.id}/edit`)}>Редактировать</button>
            <button className="pn-button" onClick={() => remove(course)}>Удалить</button>
          </article>
        ))}
      </div>
      <Pagination page={page.page} pageCount={page.pageCount} onPage={page.setPage} />
    </AdminPanel>
  )
}

function UsersPanel({ availableRoles, instruments, query, roleFilter, users, onChange }: { availableRoles: AdminUser['role'][]; instruments: Instrument[]; query: string; roleFilter: AdminUser['role'] | 'all'; users: AdminUser[]; onChange: (users: AdminUser[]) => void }) {
  const rows = filterRows(users, query, (user) => `${user.name} ${user.email} ${user.role} ${user.instrument}`)
    .filter((user) => roleFilter === 'all' || user.role === roleFilter)
  const page = usePagedRows(rows)
  const [editing, setEditing] = useState<AdminUser | null>(null)
  const [banning, setBanning] = useState<AdminUser | null>(null)
  const [banReason, setBanReason] = useState('rules')
  const [customBanReason, setCustomBanReason] = useState('')

  const changeRole = async (user: AdminUser, role: AdminUser['role']) => {
    try {
      const response = await putJson<{ user: AdminUser }>(`/api/admin/users/${user.id}`, {
        name: user.name || '',
        email: user.email || '',
        role,
        level: user.level,
        avatar: user.avatar,
        instrumentIds: user.instrumentIds,
        teacherStatus: role === 'teacher' ? user.teacherStatus ?? 'ожидает' : null,
      })
      onChange(users.map((item) => item.id === user.id ? response.user : item))
      toast.success('Роль обновлена.')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  const updateBan = async (user: AdminUser, isBanned: boolean, reason?: string | null) => {
    try {
      const response = await patchJson<{ user: AdminUser }>(`/api/admin/users/${user.id}/ban`, {
        isBanned,
        reason: isBanned ? reason : null,
      })
      onChange(users.map((item) => item.id === user.id ? response.user : item))
      toast.success(isBanned ? 'Пользователь заблокирован.' : 'Пользователь разблокирован.')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  const openBanDialog = (user: AdminUser) => {
    setBanning(user)
    setBanReason(banSelectValue(user) === 'other' ? 'other' : user.banReason || 'rules')
    setCustomBanReason(user.banReason && !fixedBanReasonValues.includes(user.banReason) ? user.banReason : '')
  }

  const saveBan = async () => {
    if (!banning) return
    const reason = banReason === 'other' ? customBanReason.trim() : banReason
    if (!reason) {
      toast.error('Укажите причину.')
      return
    }

    await updateBan(banning, true, reason)
    setBanning(null)
  }

  return (
    <AdminPanel title="Пользователи" aside={`${rows.length} записей`} actionLabel="Создать пользователя" onAction={() => setEditing(emptyUser())}>
      <Dialog open={Boolean(editing)} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent className="profile-dialog admin-modal">
          <DialogHeader>
            <DialogTitle>{editing?.id ? 'Редактировать пользователя' : 'Создать пользователя'}</DialogTitle>
            <DialogDescription>Заполните профиль, роль, уровень и интересующие инструменты.</DialogDescription>
          </DialogHeader>
          {editing && <UserForm availableRoles={availableRoles} instruments={instruments} user={editing} onCancel={() => setEditing(null)} onSaved={(user) => {
            onChange(users.some((item) => item.id === user.id) ? users.map((item) => item.id === user.id ? user : item) : [user, ...users])
            setEditing(null)
          }} />}
        </DialogContent>
      </Dialog>
      <Dialog open={Boolean(banning)} onOpenChange={(open) => !open && setBanning(null)}>
        <DialogContent className="profile-dialog admin-modal admin-ban-dialog">
          <DialogHeader>
            <DialogTitle>Заблокировать пользователя</DialogTitle>
            <DialogDescription>{banning?.name || banning?.email || 'Пользователь'} не сможет войти в аккаунт.</DialogDescription>
          </DialogHeader>
          <div className="admin-ban-dialog-body">
            <FieldLabel label="Причина">
              <select className="pn-select" value={banReason} onChange={(event) => setBanReason(event.target.value)}>
                {banReasons.filter((reason) => reason.value).map((reason) => <option key={reason.value} value={reason.value}>{reason.label}</option>)}
              </select>
            </FieldLabel>
            {banReason === 'other' && (
              <FieldLabel label="Комментарий">
                <textarea className="pn-textarea" value={customBanReason} onChange={(event) => setCustomBanReason(event.target.value)} placeholder="Напишите причину блокировки" />
              </FieldLabel>
            )}
            <div className="editor-actions">
              <button type="button" className="pn-button is-dark" onClick={saveBan}>Заблокировать</button>
              <button type="button" className="pn-button" onClick={() => setBanning(null)}>Отмена</button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
      <div className="admin-table">
        {page.rows.map((user) => (
          <article className="admin-row" key={user.id}>
            <span><strong>{user.name || 'Без имени'}</strong><em>{user.email || 'без email'} · {roleLabel(user.role)}{user.role === 'teacher' ? ` · ${user.teacherStatus || 'ожидает'}` : ''} · {user.instrument || 'инструмент не выбран'}{user.isBanned ? ` · ${banStatusText(user)}` : ''}</em></span>
            <select className="pn-select admin-row-select" value={user.role} onChange={(event) => changeRole(user, event.target.value as AdminUser['role'])}>
              {availableRoles.map((role) => <option key={role} value={role}>{roleLabel(role)}</option>)}
            </select>
            {user.isBanned
              ? <button type="button" className="pn-button admin-row-action" onClick={() => updateBan(user, false)}>Разблокировать</button>
              : <button type="button" className="pn-button admin-row-action" onClick={() => openBanDialog(user)}>Заблокировать</button>}
          </article>
        ))}
      </div>
      <Pagination page={page.page} pageCount={page.pageCount} onPage={page.setPage} />
    </AdminPanel>
  )
}

function UserForm({ availableRoles, instruments, user, onCancel, onSaved }: { availableRoles: AdminUser['role'][]; instruments: Instrument[]; user: AdminUser; onCancel: () => void; onSaved: (user: AdminUser) => void }) {
  const [form, setForm] = useState({ ...user, password: '' })
  const [uploadProgress, setUploadProgress] = useState<UploadProgressState | null>(null)
  const [touched, setTouched] = useState<Record<string, boolean>>({})
  const isNew = !user.id
  const errors = validateUserForm(form, isNew)
  const showError = (field: string) => touched[field] ? errors[field] : ''
  const markTouched = (field: string) => setTouched((current) => ({ ...current, [field]: true }))
  const isValid = Object.keys(errors).length === 0

  const toggleInstrument = (id: string) => {
    setForm((current) => ({
      ...current,
      instrumentIds: current.instrumentIds.includes(id)
        ? current.instrumentIds.filter((item) => item !== id)
        : [...current.instrumentIds, id],
    }))
  }

  const save = async (event: React.FormEvent) => {
    event.preventDefault()
    setTouched({
      name: true,
      email: true,
      password: true,
      instruments: true,
    })
    if (!isValid) {
      toast.error('Проверьте поля формы.')
      return
    }
    const payload = {
      name: form.name || '',
      email: form.email || '',
      password: form.password || undefined,
      role: form.role,
      level: form.level,
      avatar: form.avatar,
      instrumentIds: form.instrumentIds,
      teacherStatus: form.role === 'teacher' ? form.teacherStatus ?? 'ожидает' : null,
    }
    try {
      const response = isNew
        ? await postJson<{ user: AdminUser }>('/api/admin/users', payload)
        : await putJson<{ user: AdminUser }>(`/api/admin/users/${user.id}`, payload)
      onSaved(response.user)
      toast.success(isNew ? 'Пользователь создан.' : 'Пользователь обновлён.')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  const uploadAvatar = async (file: File | null) => {
    if (!file) return
    const validationMessage = validateImageFile(file)
    if (validationMessage) {
      toast.error(validationMessage)
      return
    }

    const data = new FormData()
    data.append('file', file)
    data.append('type', 'avatar')
    setUploadProgress(initialUploadProgress())
    try {
      const response = await uploadFormData<{ path: string }>('/api/admin/uploads', data, setUploadProgress)
      setForm((current) => ({ ...current, avatar: response.path }))
      toast.success('Фото прикреплено.')
    } catch {
      setUploadProgress((current) => current ? { ...current, status: 'error' } : null)
      toast.error('Не удалось загрузить фото.')
    }
  }

  return (
    <form className="pn-card pn-card-body admin-edit-form" onSubmit={save} noValidate>
      <FieldLabel label="Имя">
        <input className="pn-input" value={form.name || ''} onBlur={() => markTouched('name')} onChange={(event) => setForm({ ...form, name: event.target.value })} aria-invalid={Boolean(showError('name'))} required />
        {showError('name') && <p className="field-error">{showError('name')}</p>}
      </FieldLabel>
      <FieldLabel label="Email">
        <input className="pn-input" type="email" value={form.email || ''} onBlur={() => markTouched('email')} onChange={(event) => setForm({ ...form, email: event.target.value })} aria-invalid={Boolean(showError('email'))} />
        {showError('email') && <p className="field-error">{showError('email')}</p>}
      </FieldLabel>
      <FieldLabel label={isNew ? 'Пароль' : 'Новый пароль'}>
        <input className="pn-input" type="password" value={form.password} onBlur={() => markTouched('password')} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder={isNew ? '' : 'Оставьте пустым, если не меняется'} aria-invalid={Boolean(showError('password'))} required={isNew} />
        {showError('password') && <p className="field-error">{showError('password')}</p>}
      </FieldLabel>
      <FieldLabel label="Фото профиля">
        <label className="profile-file-control">
          <span>{form.avatar ? 'Фото прикреплено' : 'Прикрепить фото'}</span>
          <input type="file" accept={imageAccept} onChange={(event) => uploadAvatar(event.target.files?.[0] ?? null)} />
        </label>
        <MediaAttachmentPreview value={form.avatar} kind="image" emptyText="Фото пока не выбрано." onRemove={() => setForm((current) => ({ ...current, avatar: '' }))} />
        <UploadProgress progress={uploadProgress} />
      </FieldLabel>
      <FieldLabel label="Роль">
        <select className="pn-select" value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value as AdminUser['role'] })}>
          {availableRoles.map((role) => <option key={role} value={role}>{roleLabel(role)}</option>)}
        </select>
      </FieldLabel>
      {form.role === 'teacher' && (
        <FieldLabel label="Статус учителя">
          <select className="pn-select" value={form.teacherStatus || 'ожидает'} onChange={(event) => setForm({ ...form, teacherStatus: event.target.value as AdminUser['teacherStatus'] })}>
            {teacherStatuses.map((status) => <option key={status}>{status}</option>)}
          </select>
        </FieldLabel>
      )}
      <FieldLabel label="Уровень">
        <select className="pn-select" value={form.level || 'Начинающий'} onChange={(event) => setForm({ ...form, level: event.target.value })}>
          {levels.map((level) => <option key={level}>{level}</option>)}
        </select>
      </FieldLabel>
      <FieldLabel label="Инструменты">
        <div className="dashboard-chip-list profile-instrument-picker" aria-invalid={Boolean(showError('instruments'))} onBlur={() => markTouched('instruments')}>
          {instruments.map((instrument) => (
            <label className={`dashboard-chip ${form.instrumentIds.includes(instrument.id) ? 'is-selected' : ''}`} key={instrument.id}>
              <input type="checkbox" checked={form.instrumentIds.includes(instrument.id)} onChange={() => toggleInstrument(instrument.id)} />
              {instrument.name}
            </label>
          ))}
        </div>
        {showError('instruments') && <p className="field-error">{showError('instruments')}</p>}
      </FieldLabel>
      <div className="editor-actions">
        <button className="pn-button is-dark" disabled={!isValid}>Сохранить</button>
        <button type="button" className="pn-button" onClick={onCancel}>Отмена</button>
      </div>
    </form>
  )
}

function InstrumentsPanel({ instruments, query, onChange }: { instruments: Instrument[]; query: string; onChange: (items: Instrument[]) => void }) {
  const rows = filterRows(instruments, query, (instrument) => `${instrument.id} ${instrument.name} ${instrument.description}`)
  const page = usePagedRows(rows)
  const [editing, setEditing] = useState<Instrument | null>(null)

  const remove = async (instrument: Instrument) => {
    if (!window.confirm(`Удалить инструмент "${instrument.name}"?`)) return
    try {
      await deleteJson('/api/admin/instruments/' + instrument.id)
      onChange(instruments.filter((item) => item.id !== instrument.id))
      toast.success('Инструмент удалён.')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  return (
    <AdminPanel title="Инструменты" aside={`${rows.length} записей`} actionLabel="Создать инструмент" onAction={() => setEditing(emptyInstrument())}>
      <Dialog open={Boolean(editing)} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent className="profile-dialog admin-modal">
          <DialogHeader>
            <DialogTitle>{editing?.id ? 'Редактировать инструмент' : 'Создать инструмент'}</DialogTitle>
            <DialogDescription>Настройте slug, название, изображение и описание инструмента.</DialogDescription>
          </DialogHeader>
          {editing && <InstrumentForm instrument={editing} onCancel={() => setEditing(null)} onSaved={(instrument) => {
            onChange(instruments.some((item) => item.id === instrument.id) ? instruments.map((item) => item.id === instrument.id ? instrument : item) : [...instruments, instrument])
            setEditing(null)
          }} />}
        </DialogContent>
      </Dialog>
      <div className="admin-table">
        {page.rows.map((instrument) => (
          <article className="admin-row" key={instrument.id}>
            <span><strong>{instrument.name}</strong><em>{instrument.id} · {instrument.courseCount} курсов</em></span>
            <button className="pn-button" onClick={() => setEditing(instrument)}>Редактировать</button>
            <button className="pn-button" onClick={() => remove(instrument)}>Удалить</button>
          </article>
        ))}
      </div>
      <Pagination page={page.page} pageCount={page.pageCount} onPage={page.setPage} />
    </AdminPanel>
  )
}

function InstrumentForm({ instrument, onCancel, onSaved }: { instrument: Instrument; onCancel: () => void; onSaved: (instrument: Instrument) => void }) {
  const [form, setForm] = useState(instrument)
  const [uploadProgress, setUploadProgress] = useState<UploadProgressState | null>(null)
  const isNew = !instrument.id

  const save = async (event: React.FormEvent) => {
    event.preventDefault()
    const payload = {
      slug: form.id,
      name: form.name,
      image: form.image,
      description: form.description,
    }
    try {
      const response = isNew
        ? await postJson<{ instrument: Instrument }>('/api/admin/instruments', payload)
        : await putJson<{ instrument: Instrument }>(`/api/admin/instruments/${instrument.id}`, payload)
      onSaved(response.instrument)
      toast.success(isNew ? 'Инструмент создан.' : 'Инструмент обновлён.')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  const uploadImage = async (file: File | null) => {
    if (!file) return
    const validationMessage = validateImageFile(file)
    if (validationMessage) {
      toast.error(validationMessage)
      return
    }

    const data = new FormData()
    data.append('file', file)
    data.append('type', 'image')
    setUploadProgress(initialUploadProgress())

    try {
      const response = await uploadFormData<{ path: string }>('/api/admin/uploads', data, setUploadProgress)
      setForm((current) => ({ ...current, image: response.path }))
      toast.success('Изображение прикреплено.')
    } catch (error) {
      setUploadProgress((current) => current ? { ...current, status: 'error' } : null)
      toast.error(errorMessage(error))
    }
  }

  return (
    <form className="pn-card pn-card-body admin-edit-form" onSubmit={save}>
      <FieldLabel label="Slug"><input className="pn-input" value={form.id} onChange={(event) => setForm({ ...form, id: event.target.value })} placeholder="guitar" required /></FieldLabel>
      <FieldLabel label="Название"><input className="pn-input" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Гитара" required /></FieldLabel>
      <FieldLabel label="Изображение">
        <FileControl id={`instrument-image-${form.id || 'new'}`} label={form.image ? 'Изображение прикреплено' : 'Добавить изображение'} accept={imageAccept} onChange={uploadImage} />
        <MediaAttachmentPreview value={form.image} kind="image" emptyText="Изображение пока не выбрано." onRemove={() => setForm((current) => ({ ...current, image: '' }))} />
        <UploadProgress progress={uploadProgress} />
      </FieldLabel>
      <FieldLabel label="Описание"><textarea className="pn-textarea" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="Коротко опишите направление и что в него входит" required /></FieldLabel>
      {!isNew && <div className="readonly-field"><span>Количество курсов</span><strong>{form.courseCount}</strong><em>Рассчитывается из базы данных</em></div>}
      <div className="editor-actions">
        <button className="pn-button is-dark">Сохранить</button>
        <button type="button" className="pn-button" onClick={onCancel}>Отмена</button>
      </div>
    </form>
  )
}

function AdminPanel({ title, aside, actionLabel, onAction, children }: { title: string; aside: string; actionLabel?: string; onAction?: () => void; children: React.ReactNode }) {
  return (
    <section className="admin-panel">
      <div className="admin-panel-heading">
        <SectionTitle title={title} aside={aside} />
        {actionLabel && <button className="pn-button is-dark" onClick={onAction}>{actionLabel}</button>}
      </div>
      {children}
    </section>
  )
}

function AdminFilter({
  allLabel,
  label,
  onChange,
  options,
  value,
}: {
  allLabel: string
  label: string
  onChange: (value: string) => void
  options: string[]
  value: string
}) {
  return (
    <label className="catalog-filter">
      <span>{label}</span>
      <select className="pn-select" value={value} onChange={(event) => onChange(event.target.value)}>
        <option value="all">{allLabel}</option>
        {options.map((option) => <option key={option} value={option}>{option}</option>)}
      </select>
    </label>
  )
}

function filterRows<T>(rows: T[], query: string, getText: (row: T) => string) {
  const value = query.trim().toLowerCase()
  return value ? rows.filter((row) => getText(row).toLowerCase().includes(value)) : rows
}

function courseFilterOptions(values: Array<string | null | undefined>) {
  return Array.from(new Set(values.map((value) => value || '').filter(Boolean))).sort((first, second) => first.localeCompare(second, 'ru'))
}

function validateUserForm(form: AdminUser & { password: string }, isNew: boolean): Record<string, string> {
  const errors: Record<string, string> = {}
  const email = (form.email || '').trim()
  const password = form.password || ''

  if (!(form.name || '').trim()) {
    errors.name = 'Введите имя.'
  }

  if (email && !isValidEmail(email)) {
    errors.email = 'Введите корректный email.'
  }

  if (isNew && password.length < 6) {
    errors.password = 'Пароль должен быть не короче 6 символов.'
  }

  if (!isNew && password && password.length < 6) {
    errors.password = 'Новый пароль должен быть не короче 6 символов.'
  }

  if (form.role === 'teacher' && form.instrumentIds.length === 0) {
    errors.instruments = 'Для учителя выберите хотя бы один инструмент.'
  }

  return errors
}

function emptyUser(): AdminUser {
  return {
    id: '',
    name: '',
    email: '',
    avatar: '',
    role: 'user',
    teacherStatus: null,
    isBanned: false,
    banReason: null,
    mustChangeEmail: false,
    instrument: '',
    level: 'Начинающий',
    instrumentIds: [],
  }
}

function emptyInstrument(): Instrument {
  return {
    id: '',
    name: '',
    image: '',
    description: '',
    courseCount: 0,
  }
}

function roleLabel(role: AdminUser['role']) {
  if (role === 'admin') return 'Администратор'
  if (role === 'moderator') return 'Модератор'
  if (role === 'teacher') return 'Учитель'
  return 'Ученик'
}

function banSelectValue(user: AdminUser) {
  if (!user.isBanned) return ''
  const reason = user.banReason || 'rules'
  return fixedBanReasonValues.includes(reason) ? reason : 'other'
}

function banStatusText(user: AdminUser) {
  const reason = user.banReason?.trim()
  if (!reason) return 'заблокирован'

  const fixedReason = banReasons.find((item) => item.value === reason)
  return `заблокирован: ${fixedReason?.label ?? reason}`
}

function sectionHint(tab: Tab) {
  if (tab === 'Курсы') return 'Создание, редактирование и удаление программ'
  if (tab === 'Пользователи') return 'Роли и блокировки'
  return 'Каталог направлений и обложки'
}

function FieldLabel({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="form-field">
      <span>{label}</span>
      {children}
    </div>
  )
}

function usePagedRows<T>(rows: T[]) {
  const [page, setPage] = useState(1)
  const pageCount = Math.max(1, Math.ceil(rows.length / adminPageSize))
  const currentPage = Math.min(page, pageCount)

  return {
    page: currentPage,
    pageCount,
    setPage,
    rows: rows.slice((currentPage - 1) * adminPageSize, currentPage * adminPageSize),
  }
}

function Pagination({ page, pageCount, onPage }: { page: number; pageCount: number; onPage: (page: number) => void }) {
  if (pageCount <= 1) {
    return null
  }

  return (
    <div className="table-pagination">
      <button className="pn-button" disabled={page === 1} onClick={() => onPage(Math.max(1, page - 1))}>Назад</button>
      <div className="pagination-pages">
        {Array.from({ length: pageCount }, (_, index) => index + 1).map((pageNumber) => (
          <button
            className={`pagination-page ${pageNumber === page ? 'is-active' : ''}`}
            key={pageNumber}
            type="button"
            onClick={() => onPage(pageNumber)}
          >
            {pageNumber}
          </button>
        ))}
      </div>
      <button className="pn-button" disabled={page === pageCount} onClick={() => onPage(Math.min(pageCount, page + 1))}>Далее</button>
    </div>
  )
}

function tabFromLocation(): Tab | null {
  const section = new URLSearchParams(window.location.search).get('section')
  const match: Record<string, Tab> = {
    courses: 'Курсы',
    users: 'Пользователи',
    instruments: 'Инструменты',
  }

  return section ? match[section] ?? null : null
}

function FileControl({ id, label, accept, onChange }: { id: string; label: string; accept: string; onChange: (file: File | null) => void }) {
  return (
    <>
      <input className="file-input" id={id} type="file" accept={accept} onChange={(event) => onChange(event.target.files?.[0] ?? null)} />
      <label className="profile-file-control" htmlFor={id}>
        <span>{label}</span>
      </label>
    </>
  )
}

function errorMessage(error: unknown) {
  return error instanceof Error ? error.message : 'Не удалось выполнить действие.'
}
