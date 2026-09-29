import type { Indicator, MunicipalityIndicators } from '../types/indicator'

function isIndicator(value: unknown): value is Indicator {
  if (typeof value !== 'object' || value === null) return false

  return 'code' in value && typeof value.code === 'string' && value.code.trim().length > 0
    && 'name' in value && typeof value.name === 'string' && value.name.trim().length > 0
    && 'value' in value && typeof value.value === 'string' && /^-?\d+(?:\.\d+)?$/.test(value.value)
    && 'unit' in value && typeof value.unit === 'string' && value.unit.trim().length > 0
    && 'reference_year' in value && Number.isInteger(value.reference_year) && Number(value.reference_year) > 0
}

function isMunicipalityIndicators(value: unknown, municipalityIbgeCode: number): value is MunicipalityIndicators {
  if (typeof value !== 'object' || value === null
    || !('municipality' in value) || typeof value.municipality !== 'object' || value.municipality === null
    || !('indicators' in value) || !Array.isArray(value.indicators)) return false

  const municipality = value.municipality
  return 'ibge_code' in municipality && municipality.ibge_code === municipalityIbgeCode
    && 'name' in municipality && typeof municipality.name === 'string' && municipality.name.trim().length > 0
    && value.indicators.every(isIndicator)
    && new Set(value.indicators.map((indicator) => `${indicator.code}:${indicator.reference_year}`)).size === value.indicators.length
}

export async function getMunicipalityIndicators(municipalityIbgeCode: number, signal: AbortSignal): Promise<MunicipalityIndicators> {
  const apiUrl = import.meta.env.VITE_API_URL?.trim().replace(/\/+$/, '')
  if (!apiUrl) throw new Error('A URL da API não está configurada.')

  let response: Response
  try {
    response = await fetch(`${apiUrl}/api/municipalities/${municipalityIbgeCode}/indicators`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.any([signal, AbortSignal.timeout(15000)]),
    })
  } catch (error) {
    if (signal.aborted) throw error
    throw new Error('Não foi possível conectar à API de indicadores. Tente recarregar a página.')
  }

  if (!response.ok) {
    throw new Error(`Não foi possível carregar os indicadores (HTTP ${response.status}).`)
  }

  let data: unknown
  try {
    data = await response.json()
  } catch {
    throw new Error('A API de indicadores retornou uma resposta inválida.')
  }

  if (!isMunicipalityIndicators(data, municipalityIbgeCode)) {
    throw new Error('A API de indicadores retornou dados inválidos.')
  }

  return data
}
