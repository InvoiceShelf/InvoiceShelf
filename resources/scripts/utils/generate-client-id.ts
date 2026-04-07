import Guid from 'guid'

export function generateClientId(): string {
  return Guid.raw()
}
