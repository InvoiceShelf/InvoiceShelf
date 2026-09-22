
/**
 * The stored `type` of a field definition, which is also the name of the
 * component it is edited with: `Input` renders `types/InputType.vue`.
 *
 * These are the values, not the labels shown in the editor -- the dropdown
 * offers "Text", "URL" and "Select Field" for `Input`, `Url` and `Dropdown`.
 */
export type CustomFieldType =
  | 'Input'
  | 'TextArea'
  | 'Phone'
  | 'Url'
  | 'Number'
  | 'Dropdown'
  | 'Switch'
  | 'Date'
  | 'Time'
  | 'DateTime'

export type CustomFieldModelType =
  | 'Customer'
  | 'Invoice'
  | 'Estimate'
  | 'Payment'
  | 'Expense'

export interface CustomField {
  id: number
  name: string
  slug: string
  label: string
  model_type: CustomFieldModelType
  /** 'internal' or 'document'; whether it prints on the record's PDF. */
  placement?: string
  /** Bounds and an optional pattern an answer must satisfy. */
  validation?: Record<string, unknown> | null
  type: CustomFieldType
  placeholder: string | null
  options: string[] | null
  boolean_answer: boolean | null
  date_answer: string | null
  time_answer: string | null
  string_answer: string | null
  number_answer: number | null
  date_time_answer: string | null
  is_required: boolean
  in_use: boolean
  order: number | null
  company_id: number
  default_answer: string | boolean | number | null
}

export interface CustomFieldValue {
  id: number
  custom_field_valuable_type: string
  custom_field_valuable_id: number
  type: CustomFieldType
  boolean_answer: boolean | null
  date_answer: string | null
  time_answer: string | null
  string_answer: string | null
  number_answer: number | null
  date_time_answer: string | null
  custom_field_id: number
  company_id: number
  default_answer: string | boolean | number | null
  default_formatted_answer: string | null
  custom_field?: CustomField
}
