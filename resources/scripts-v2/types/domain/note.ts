import type { Company } from './company'

export type NoteType = 'Invoice' | 'Estimate' | 'Payment'

export interface Note {
  id: number
  type: NoteType
  name: string
  notes: string
  is_default: boolean | null
  company?: Company
}
