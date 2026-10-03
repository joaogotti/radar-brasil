import type { Indicator } from '../types/indicator'
import { demographicIndicators, livingConditionIndicators, censusIndicator } from './demographicIndicators.ts'
import { economicComposition, latestEconomicIndicator } from './economicIndicators.ts'
import { idebSeries, latestIdebIndicator, schoolCensusIndicators, schoolCensusIndicator } from './educationIndicators.ts'
import { formatIndicator } from './formatIndicator.ts'
import { formatAge, formatPercentage, formatRatio } from './formatDemographicIndicator.ts'
import { formatReais, formatThousandReais } from './formatEconomicIndicator.ts'
import { formatEducationCount, formatIdeb } from './formatEducationIndicator.ts'

interface ComparisonIndicator {
  code: string
  label: string
  unit?: string
  description?: string
  format: (value: string) => string
}

export interface ComparisonGroup {
  id: string
  title: string
  note?: string
  indicators: ComparisonIndicator[]
}

export const comparisonGroups: ComparisonGroup[] = [
  { id: 'overview', title: 'Visão geral', indicators: [
    { code: 'population_census', label: 'População', format: (value) => formatIndicator('population_census', value) },
    { code: 'territorial_area', label: 'Área territorial', unit: 'km²', format: (value) => formatIndicator('territorial_area', value) },
    { code: 'population_density', label: 'Densidade demográfica', unit: 'hab./km²', format: (value) => formatIndicator('population_density', value) },
  ] },
  { id: 'economy', title: 'Economia', note: 'Valores a preços correntes. VAB Serviços exclui Administração pública, apresentada separadamente.', indicators: [
    { code: 'municipal_gdp', label: 'PIB', format: formatThousandReais },
    { code: 'municipal_gdp_per_capita', label: 'PIB per capita', format: formatReais },
    { code: 'agriculture_gva', label: 'VAB Agropecuária', format: formatThousandReais },
    { code: 'industry_gva', label: 'VAB Indústria', format: formatThousandReais },
    { code: 'services_gva', label: 'VAB Serviços', format: formatThousandReais },
    { code: 'public_administration_gva', label: 'VAB Administração pública', format: formatThousandReais },
  ] },
  { id: 'demography', title: 'Demografia', indicators: demographicIndicators.map((item) => ({ ...item, format: item.code === 'median_age' ? formatAge : formatRatio })) },
  { id: 'living', title: 'Condições de vida', note: 'Os indicadores domiciliares referem-se aos domicílios particulares permanentes ocupados (DPPO).', indicators: livingConditionIndicators.map((item) => ({ ...item, format: formatPercentage })) },
  { id: 'education', title: 'Educação', note: 'Matrículas não equivalem a alunos únicos. Docentes são distintos dentro do município, segundo o Censo Escolar.', indicators: [
    ...schoolCensusIndicators.map(({ code, label }) => ({ code, label, format: formatEducationCount })),
    ...idebSeries.map(({ code, label }) => ({ code, label: `IDEB — ${label.toLowerCase()} — rede pública`, format: formatIdeb })),
  ] },
]

export function comparisonValues(data: Indicator[]): Record<string, Indicator | undefined> {
  const composition = economicComposition(data)
  const values: Record<string, Indicator | undefined> = {}
  for (const group of comparisonGroups) {
    for (const { code } of group.indicators) {
      if (group.id === 'economy') {
        values[code] = code.endsWith('_gva') ? composition?.sectors.find((sector) => sector.code === code)?.indicator : latestEconomicIndicator(data, code)
      } else if (group.id === 'education') {
        const census = schoolCensusIndicators.find((item) => item.code === code)
        const ideb = idebSeries.find((item) => item.code === code)
        values[code] = census ? schoolCensusIndicator(data, census.code) : ideb ? latestIdebIndicator(data, ideb.code) : undefined
      } else {
        values[code] = censusIndicator(data, code)
      }
    }
  }
  return values
}

export function differentReferenceYears(a?: Indicator, b?: Indicator): number[] {
  return a && b && a.reference_year !== b.reference_year ? [a.reference_year, b.reference_year] : []
}
