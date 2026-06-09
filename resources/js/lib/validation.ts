export const emailPattern = /^[^\s@]+@(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i

export function isValidEmail(value: string) {
  return emailPattern.test(value.trim())
}
