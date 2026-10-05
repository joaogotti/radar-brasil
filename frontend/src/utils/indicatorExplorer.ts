import type { Indicator } from '../types/indicator.ts'
import { indicatorCatalog } from './indicatorCatalog.ts'

export function hasMunicipalitySearchCriteria(search: string, state: number | null): boolean {
  return search.trim().length > 0 || state !== null
}

export function parseIndicatorSelection(params: URLSearchParams) {
  const codes = params.getAll('indicador')
  const municipalities = params.getAll('municipio')
  const found = codes.length === 1 ? indicatorCatalog.find(item => item.code === codes[0]) : undefined
  const validMunicipality = municipalities.length === 1 && /^[1-9][0-9]{6}$/.test(municipalities[0])
  return {
    indicator: found ?? indicatorCatalog[0],
    municipality: validMunicipality ? Number(municipalities[0]) : null,
    invalidIndicator: codes.length > 0 && !found,
    invalidMunicipality: municipalities.length > 0 && !validMunicipality,
  }
}

export function serializeIndicatorSelection(code: string, municipality: number | null): URLSearchParams {
  const params = new URLSearchParams({ indicador: code })
  if (municipality !== null) params.set('municipio', String(municipality))
  return params
}

export function indicatorObservations(data: readonly Indicator[], code: string): Indicator[] {
  return data.filter(item => item.code === code).sort((a, b) => b.reference_year - a.reference_year)
}
