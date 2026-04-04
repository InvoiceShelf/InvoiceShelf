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
  formatted_created_at: string
  abilities: Ability[]
}
