import type { MunicipalityIndicators } from './indicator'

export type ObservationStatus = 'available' | 'unavailable' | 'not_applicable' | 'suppressed' | 'omitted'
export type DistributionAvailability = 'not_loaded' | 'unavailable' | 'partial' | 'complete'

export interface AgeGroup {
  ibge_code: number
  label: string
  order: number
  min_age: number
  max_age: number | null
}

export interface AgeDistributionCell {
  sex_code: 4 | 5
  sex_label: string
  age_group: AgeGroup
  population: number | null
  population_status: ObservationStatus
  percentage: string | null
  percentage_status: ObservationStatus
}

export interface AgeDistribution {
  municipality: MunicipalityIndicators['municipality']
  reference_year: number
  availability: DistributionAvailability
  source: {
    name: string
    aggregate_id: number
    population_variable_id: number
    percentage_variable_id: number
    percentage_basis: 'total_municipal_population'
  }
  data: AgeDistributionCell[]
}
