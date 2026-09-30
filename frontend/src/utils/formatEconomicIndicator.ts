const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const abbreviated = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 })

function shiftDecimal(value: string, places: number): string {
  const negative = value.startsWith('-')
  const [integer, fraction = ''] = value.replace(/^-/, '').split('.')
  const digits = integer + fraction
  const point = integer.length + places
  const shifted = point <= 0 ? `0.${'0'.repeat(-point)}${digits}`
    : point >= digits.length ? digits + '0'.repeat(point - digits.length)
      : `${digits.slice(0, point)}.${digits.slice(point)}`
  return `${negative ? '-' : ''}${shifted.replace(/^0+(?=\d)/, '')}`
}

export function formatReais(value: string): string {
  // Intl accepts decimal strings at runtime; keep the source precision until formatting.
  return currency.format(value as unknown as number)
}

export function formatThousandReais(value: string, compact = true): string {
  const reais = shiftDecimal(value, 3)
  const magnitude = BigInt(reais.replace(/^-/, '').split('.')[0])
  if (compact && magnitude >= 1_000_000n) {
    const billions = magnitude >= 1_000_000_000n
    const scaled = shiftDecimal(reais, billions ? -9 : -6)
    return `R$ ${abbreviated.format(scaled as unknown as number)} ${billions ? 'bi' : 'mi'}`
  }
  return formatReais(reais)
}
