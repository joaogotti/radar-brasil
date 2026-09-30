import type { Indicator } from '../types/indicator'

const sectors = [
  { code: 'agriculture_gva', label: 'Agropecuária' },
  { code: 'industry_gva', label: 'Indústria' },
  { code: 'services_gva', label: 'Serviços' },
  { code: 'public_administration_gva', label: 'Administração pública' },
]

export function latestEconomicIndicator(data: Indicator[], code: string): Indicator | undefined {
  return data.filter((item) => item.code === code)
    .reduce<Indicator | undefined>((latest, item) =>
      !latest || item.reference_year > latest.reference_year ? item : latest, undefined)
}

export function economicComposition(data: Indicator[]) {
  const series = sectors.map((sector) => data.filter((item) => item.code === sector.code))
  const commonYears = series[0].map((item) => item.reference_year)
    .filter((year) => series.every((items) => items.some((item) => item.reference_year === year)))
  if (commonYears.length === 0) return null
  const year = Math.max(...commonYears)
  return {
    year,
    sectors: sectors.map((sector, index) => ({
      ...sector,
      indicator: series[index].find((item) => item.reference_year === year)!,
    })),
  }
}

export function gdpHistory(data: Indicator[]): Indicator[] {
  return data.filter((item) => item.code === 'municipal_gdp')
    .sort((a, b) => a.reference_year - b.reference_year)
}

export function continuousGdpSegments(history: Indicator[]): Indicator[][] {
  const segments: Indicator[][] = []
  for (const item of history) {
    const previous = segments.at(-1)?.at(-1)
    if (!previous || item.reference_year !== previous.reference_year + 1) segments.push([])
    segments.at(-1)!.push(item)
  }
  return segments
}
