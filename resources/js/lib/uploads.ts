export const imageAccept = '.jpg,.jpeg,.png,.webp,.gif'
export const videoAccept = '.mp4,.webm,.mov'
export const documentAccept = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx'

const imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
const videoTypes = ['video/mp4', 'video/webm', 'video/quicktime']
const maxImageSize = 5 * 1024 * 1024
const maxVideoSize = 100 * 1024 * 1024
const documentExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx']
const maxDocumentSize = 8 * 1024 * 1024

export function validateImageFile(file: File | null): string | null {
  if (!file) return null
  if (!imageTypes.includes(file.type)) return 'Формат не подходит.'
  if (file.size > maxImageSize) return 'Файл слишком большой.'
  return null
}

export function validateVideoFile(file: File | null): string | null {
  if (!file) return null
  if (!videoTypes.includes(file.type)) return 'Формат не подходит.'
  if (file.size > maxVideoSize) return 'Файл слишком большой.'
  return null
}

export function validateDocumentFile(file: File | null): string | null {
  if (!file) return null
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
  if (!documentExtensions.includes(extension)) return 'Формат не подходит.'
  if (file.size > maxDocumentSize) return 'Файл слишком большой.'
  return null
}
