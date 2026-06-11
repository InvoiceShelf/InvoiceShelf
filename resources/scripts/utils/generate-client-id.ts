export function generateClientId(): string {
  return crypto.randomUUID()
}
