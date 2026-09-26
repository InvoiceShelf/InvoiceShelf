export interface Ability {
  id: number
  name: string
  title: string | null
  entity_id: number | null
  entity_type: string | null
  only_owned: boolean
  scope: number | null
  created_at: string
  updated_at: string
}

export interface Role {
  id: number
  name: string
  title: string | null
  level: number | null
  /** The role preset this is a company's copy of (`owner` included), or null. */
  preset?: string | null
  formatted_created_at: string
  abilities: Ability[]
}
