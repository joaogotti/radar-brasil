import type { MunicipalitySearchItem } from '../types/municipality'

const normalize = (value: string) => value.trim().normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('pt-BR')

export function headerMunicipalityResults(items: MunicipalitySearchItem[], query: string): MunicipalitySearchItem[] {
  const search = normalize(query)
  if (!search) return []
  const rank = (item: MunicipalitySearchItem) => {
    const name = normalize(item.name)
    return name === search ? 0 : name.startsWith(search) ? 1 : 2
  }
  return [...items].sort((a, b) => rank(a) - rank(b)).slice(0, 6)
}
