import type { Indicator } from '../types/indicator'

export const schoolCensusYear = 2025
export const schoolCensusIndicators = [
  { code: 'basic_education_enrollments', label: 'Matrículas', description: 'Uma pessoa pode possuir mais de uma matrícula.' },
  { code: 'basic_education_establishments', label: 'Estabelecimentos', description: 'Estabelecimentos da educação básica com matrícula.' },
  { code: 'basic_education_teachers', label: 'Docentes', description: 'Docentes distintos no município, segundo a metodologia do Censo Escolar.' },
] as const

export const idebEditions = [2005, 2007, 2009, 2011, 2013, 2015, 2017, 2019, 2021, 2023, 2025] as const
export const idebSeries = [
  { code: 'ideb_initial_years_public', label: 'Anos iniciais' },
  { code: 'ideb_final_years_public', label: 'Anos finais' },
] as const
type IdebCode = typeof idebSeries[number]['code']

export function schoolCensusIndicator(data: Indicator[], code: typeof schoolCensusIndicators[number]['code']) {
  return data.find((item) => item.code === code && item.reference_year === schoolCensusYear)
}

export function idebHistory(data: Indicator[], code: IdebCode): Indicator[] {
  return idebEditions.flatMap((year) => {
    const item = data.find((item) => item.code === code && item.reference_year === year)
    return item ? [item] : []
  })
}

export function latestIdebIndicator(data: Indicator[], code: IdebCode) {
  return idebHistory(data, code).at(-1)
}

export function continuousIdebSegments(history: Indicator[]): Indicator[][] {
  const segments: Indicator[][] = []
  let current: Indicator[] = []
  for (const year of idebEditions) {
    const item = history.find((item) => item.reference_year === year)
    if (!item) {
      current = []
    } else {
      if (current.length === 0) segments.push(current)
      current.push(item)
    }
  }
  return segments
}
