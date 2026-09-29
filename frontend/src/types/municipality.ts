import type { State } from './state'

export interface Municipality {
  id: number
  state_id: number
  ibge_code: number
  name: string
}

export interface MunicipalitySearchItem extends Omit<Municipality, 'state_id'> {
  state: Omit<State, 'id'>
}

export interface MunicipalitySearchPage {
  data: MunicipalitySearchItem[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface MunicipalitySearchFilters {
  search: string
  state: number | null
  page: number
}
