import type { AgeDistribution, AgeDistributionCell, AgeGroup, ObservationStatus } from '../types/ageDistribution'

const statuses: ObservationStatus[] = ['available', 'unavailable', 'not_applicable', 'suppressed', 'omitted']
const record = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value)
const positiveInteger = (value: unknown): value is number => typeof value === 'number' && Number.isSafeInteger(value) && value > 0
const text = (value: unknown): value is string => typeof value === 'string' && value.trim().length > 0
const status = (value: unknown): value is ObservationStatus => typeof value === 'string' && statuses.some((item) => item === value)

function validPercentage(value: unknown): value is string {
  if (typeof value !== 'string' || !/^\d{1,3}(?:\.\d{1,6})?$/.test(value)) return false
  const [integer, fraction = ''] = value.split('.')
  const scaled = BigInt(integer) * 1_000_000n + BigInt(fraction.padEnd(6, '0'))
  return scaled <= 100_000_000n
}

function validCell(value: unknown): value is AgeDistributionCell {
  if (!record(value) || !record(value.age_group)) return false
  const age = value.age_group
  if (!positiveInteger(age.ibge_code) || !text(age.label) || !positiveInteger(age.order) || age.order > 21
    || age.min_age !== (age.order - 1) * 5 || age.max_age !== (age.order === 21 ? null : age.order * 5 - 1)) return false
  if ((value.sex_code !== 4 && value.sex_code !== 5) || value.sex_label !== (value.sex_code === 4 ? 'Homens' : 'Mulheres')
    || !status(value.population_status) || !status(value.percentage_status)) return false
  const populationValid = value.population_status === 'available'
    ? typeof value.population === 'number' && Number.isSafeInteger(value.population) && value.population >= 0 && value.population <= 2147483647
    : value.population === null
  return populationValid && (value.percentage_status === 'available' ? validPercentage(value.percentage) : value.percentage === null)
}

export function parseAgeDistribution(value: unknown, expectedCode: number): AgeDistribution {
  const invalid = () => new Error('A API de distribuição etária retornou dados inválidos.')
  if (!record(value) || !record(value.municipality) || !record(value.municipality.state) || !record(value.source)
    || !Array.isArray(value.data) || !value.data.every(validCell)) throw invalid()
  const municipality = value.municipality
  const state = municipality.state as Record<string, unknown>
  const source = value.source
  if (municipality.ibge_code !== expectedCode || !text(municipality.name)
    || !positiveInteger(state.ibge_code) || !text(state.name) || typeof state.abbreviation !== 'string' || !/^[A-Z]{2}$/.test(state.abbreviation)
    || value.reference_year !== 2022 || !text(source.name) || source.aggregate_id !== 9514
    || source.population_variable_id !== 93 || source.percentage_variable_id !== 1000093
    || source.percentage_basis !== 'total_municipal_population') throw invalid()

  const keys = new Set<string>()
  const ages = new Map<number, AgeGroup>()
  const orders = new Map<number, number>()
  for (const cell of value.data) {
    const age = cell.age_group
    const key = `${age.ibge_code}:${cell.sex_code}`
    const previous = ages.get(age.ibge_code)
    if (keys.has(key) || (orders.has(age.order) && orders.get(age.order) !== age.ibge_code)
      || (previous && (previous.order !== age.order || previous.label !== age.label || previous.min_age !== age.min_age || previous.max_age !== age.max_age))) throw invalid()
    keys.add(key)
    ages.set(age.ibge_code, age)
    orders.set(age.order, age.ibge_code)
  }
  const available = value.data.reduce((sum, cell) => sum + Number(cell.population_status === 'available') + Number(cell.percentage_status === 'available'), 0)
  const expectedAvailability = value.data.length === 0 ? 'not_loaded' : available === 0 ? 'unavailable'
    : value.data.length === 42 && available === 84 ? 'complete' : 'partial'
  if (value.availability !== expectedAvailability) throw invalid()
  return value as unknown as AgeDistribution
}

export function ageDistributionRows(data: AgeDistributionCell[]) {
  const rows = new Map<number, { age: AgeGroup, men?: AgeDistributionCell, women?: AgeDistributionCell }>()
  for (const cell of data) {
    const row = rows.get(cell.age_group.ibge_code) ?? { age: cell.age_group }
    if (cell.sex_code === 4) row.men = cell
    else row.women = cell
    rows.set(cell.age_group.ibge_code, row)
  }
  return [...rows.values()].sort((a, b) => b.age.order - a.age.order)
}

export function percentageGeometry(data: AgeDistributionCell[]) {
  const values = data.flatMap((cell) => cell.percentage_status === 'available' && cell.percentage !== null ? [Number(cell.percentage)] : [])
  const domain = Math.ceil(Math.max(0, ...values)) || 1
  return {
    domain,
    hasPercentages: values.length > 0,
    length: (cell: AgeDistributionCell | undefined, width: number) => cell?.percentage_status === 'available' && cell.percentage !== null
      ? Number(cell.percentage) / domain * width : null,
  }
}
