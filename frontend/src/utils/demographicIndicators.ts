import type { Indicator } from '../types/indicator'

export const demographicIndicators = [
  { code: 'median_age', label: 'Idade mediana', description: 'Idade que divide a população residente em duas metades.' },
  { code: 'aging_index_65_plus', label: 'Índice de envelhecimento', description: 'Pessoas de 65 anos ou mais para cada 100 pessoas de 0 a 14 anos.' },
  { code: 'sex_ratio', label: 'Razão de sexo', description: 'Homens para cada 100 mulheres.' },
] as const

export const livingConditionIndicators = [
  { code: 'literacy_rate_15_plus', label: 'Taxa de alfabetização', description: 'Pessoas de 15 anos ou mais.' },
  { code: 'households_main_water_network_pct', label: 'Rede de água como fonte principal', description: 'Domicílios com ligação à rede geral e uso como forma principal.' },
  { code: 'households_sewer_stormwater_or_connected_septic_pct', label: 'Esgotamento por rede ou fossa ligada', description: 'Inclui rede geral, rede pluvial ou fossa ligada à rede.' },
  { code: 'households_collected_garbage_pct', label: 'Lixo coletado', description: 'Coleta direta ou por caçamba do serviço de limpeza.' },
] as const

export function censusIndicator(indicators: Indicator[], code: string) {
  return indicators.find((item) => item.code === code && item.reference_year === 2022)
}
