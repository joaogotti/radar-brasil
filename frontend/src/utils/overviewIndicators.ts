import type { Indicator } from '../types/indicator'
import type { OverviewIndicatorCode } from './formatIndicator'

const cards: { code: OverviewIndicatorCode, label: string, unit: string }[] = [
  { code: 'population_census', label: 'População', unit: '' },
  { code: 'territorial_area', label: 'Área territorial', unit: 'km²' },
  { code: 'population_density', label: 'Densidade demográfica', unit: 'hab./km²' },
]

export function overviewIndicators(data: Indicator[] = []) {
  return cards.map((card) => ({
    ...card,
    indicator: data.filter((indicator) => indicator.code === card.code)
      .reduce<Indicator | undefined>((latest, indicator) =>
        !latest || indicator.reference_year > latest.reference_year ? indicator : latest, undefined),
  }))
}
