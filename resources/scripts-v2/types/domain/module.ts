export interface ModuleAuthor {
  name: string
  avatar: string
}

export interface ModuleVersion {
  module_version: string
  invoiceshelf_version: string
  created_at: string
}

export interface ModuleLink {
  name: string
  url: string
}

export interface ModuleReview {
  id: number
  rating: number
  comment: string
  user: string
  created_at: string
}

export interface ModuleScreenshot {
  url: string
  title: string | null
}

export interface ModuleFaq {
  question: string
  answer: string
}

export interface Module {
  id: number
  average_rating: number | null
  cover: string | null
  slug: string
  module_name: string
  faq: ModuleFaq[] | null
  highlights: string[] | null
  installed_module_version: string | null
  installed_module_version_updated_at: string | null
  latest_module_version: string
  latest_module_version_updated_at: string
  is_dev: boolean
  license: string | null
  long_description: string | null
  monthly_price: number | null
  name: string
  purchased: boolean
  reviews: ModuleReview[]
  screenshots: ModuleScreenshot[] | null
  short_description: string | null
  type: string | null
  yearly_price: number | null
  author_name: string
  author_avatar: string
  installed: boolean
  enabled: boolean
  update_available: boolean
  video_link: string | null
  video_thumbnail: string | null
  links: ModuleLink[] | null
}

export interface InstalledModule {
  id: number
  name: string
  version: string
  installed: boolean
  enabled: boolean
  created_at: string
  updated_at: string
}
