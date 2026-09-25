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

/** A role the super administrator defines for every company. */
export interface RolePreset {
  id: number
  key: string
  title: string
  /** The Owner preset: every ability, cannot be changed or removed. */
  is_owner: boolean
  role_name: string
  abilities: string[]
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
