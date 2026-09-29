import type { State } from './state'

export interface Indicator {
  code: string
  name: string
  value: string
  unit: string
  reference_year: number
}

export interface MunicipalityIndicators {
  municipality: {
    ibge_code: number
    name: string
    state: Omit<State, 'id'>
  }
  indicators: Indicator[]
}
