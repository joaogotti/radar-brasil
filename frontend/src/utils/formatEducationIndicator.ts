const integer = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 })
const ideb = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })

export function formatEducationCount(value: string): string {
  return integer.format(value as unknown as number)
}

export function formatIdeb(value: string): string {
  return ideb.format(value as unknown as number)
}
