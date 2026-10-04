import type { Indicator } from '../types/indicator'
import { latestEconomicIndicator } from './economicIndicators.ts'
import { formatEducationCount } from './formatEducationIndicator.ts'
import { formatReais } from './formatEconomicIndicator.ts'

const indicators = [
  { code: 'cempre_local_units', label: 'Unidades locais', format: formatEducationCount },
  { code: 'cempre_active_organizations', label: 'Empresas e organizações atuantes', format: formatEducationCount },
  { code: 'cempre_employed_personnel', label: 'Pessoal ocupado total', format: formatEducationCount },
  { code: 'cempre_salaried_personnel', label: 'Pessoal ocupado assalariado', format: formatEducationCount },
  { code: 'cempre_average_monthly_salary', label: 'Salário médio mensal', format: formatReais },
] as const

export function workAndBusinessIndicators(data: Indicator[]) {
  return indicators.map((item) => ({ ...item, indicator: latestEconomicIndicator(data, item.code) }))
}
