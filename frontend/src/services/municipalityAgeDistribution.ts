import type { AgeDistribution } from '../types/ageDistribution'
import { parseAgeDistribution } from '../utils/ageDistribution'

export class AgeDistributionRequestError extends Error {
  readonly status: number

  constructor(status: number) {
    super(status === 404 ? 'Distribuição etária não encontrada.' : 'Não foi possível carregar a distribuição etária.')
    this.status = status
  }
}

export async function getMunicipalityAgeDistribution(municipalityIbgeCode: number, signal: AbortSignal): Promise<AgeDistribution> {
  const apiUrl = import.meta.env.VITE_API_URL?.trim().replace(/\/+$/, '')
  if (!apiUrl) throw new Error('A URL da API não está configurada.')

  let response: Response
  try {
    response = await fetch(`${apiUrl}/api/municipalities/${municipalityIbgeCode}/age-distribution`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.any([signal, AbortSignal.timeout(15000)]),
    })
  } catch (error) {
    if (signal.aborted) throw error
    throw new Error('Não foi possível conectar à API de distribuição etária. Tente recarregar a página.')
  }

  if (!response.ok) {
    throw new AgeDistributionRequestError(response.status)
  }

  let data: unknown
  try {
    data = await response.json()
  } catch {
    throw new Error('A API de distribuição etária retornou uma resposta inválida.')
  }

  return parseAgeDistribution(data, municipalityIbgeCode)
}
