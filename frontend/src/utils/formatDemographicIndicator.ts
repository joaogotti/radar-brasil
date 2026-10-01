const decimal = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const age = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 6 })
const integer = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 })

export function formatRatio(value: string): string {
  return decimal.format(value as unknown as number)
}

export function formatAge(value: string): string {
  return `${age.format(value as unknown as number)} anos`
}

export function formatPercentage(value: string | null): string {
  return value === null ? 'Não disponível' : `${formatRatio(value)}%`
}

export function formatPopulation(value: number | null): string {
  return value === null ? 'Não disponível' : `${integer.format(value)} ${value === 1 ? 'pessoa' : 'pessoas'}`
}
