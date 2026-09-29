const formats = {
  population_census: new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 }),
  territorial_area: new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }),
  population_density: new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
}

export type OverviewIndicatorCode = keyof typeof formats

export function formatIndicator(code: OverviewIndicatorCode, value: string): string {
  return formats[code].format(value as unknown as number)
}
