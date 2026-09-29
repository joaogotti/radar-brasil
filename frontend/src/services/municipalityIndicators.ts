import type { Indicator, MunicipalityIndicators } from '../types/indicator'

export class IndicatorRequestError extends Error {
  readonly status: number

  constructor(status: number) {
    super(status === 404 ? 'Município não encontrado.' : 'Não foi possível carregar os indicadores. Tente novamente.')
    this.status = status
  }
}

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
  if (!('state' in municipality) || typeof municipality.state !== 'object' || municipality.state === null) return false
  const state = municipality.state
  return 'ibge_code' in municipality && municipality.ibge_code === municipalityIbgeCode
    && 'name' in municipality && typeof municipality.name === 'string' && municipality.name.trim().length > 0
    && 'ibge_code' in state && Number.isInteger(state.ibge_code) && Number(state.ibge_code) > 0
    && 'name' in state && typeof state.name === 'string' && state.name.trim().length > 0
    && 'abbreviation' in state && typeof state.abbreviation === 'string' && /^[A-Z]{2}$/.test(state.abbreviation)
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
    throw new IndicatorRequestError(response.status)
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
