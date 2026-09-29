import type { Municipality } from '../types/municipality'

function isMunicipality(value: unknown): value is Municipality {
  if (typeof value !== 'object' || value === null) return false

  return 'id' in value && Number.isInteger(value.id) && Number(value.id) > 0
    && 'state_id' in value && Number.isInteger(value.state_id) && Number(value.state_id) > 0
    && 'ibge_code' in value && Number.isInteger(value.ibge_code) && Number(value.ibge_code) > 0
    && 'name' in value && typeof value.name === 'string' && value.name.trim().length > 0
}

export async function getMunicipalities(stateIbgeCode: number, signal: AbortSignal): Promise<Municipality[]> {
  const apiUrl = import.meta.env.VITE_API_URL?.trim().replace(/\/+$/, '')
  if (!apiUrl) throw new Error('A URL da API não está configurada.')

  let response: Response
  try {
    response = await fetch(`${apiUrl}/api/states/${stateIbgeCode}/municipalities`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.any([signal, AbortSignal.timeout(15000)]),
    })
  } catch (error) {
    if (signal.aborted) throw error
    throw new Error('Não foi possível conectar à API de municípios. Tente recarregar a página.')
  }

  if (!response.ok) {
    throw new Error(`Não foi possível carregar os municípios (HTTP ${response.status}).`)
  }

  let data: unknown
  try {
    data = await response.json()
  } catch {
    throw new Error('A API de municípios retornou uma resposta inválida.')
  }

  if (!Array.isArray(data) || !data.every(isMunicipality)
    || new Set(data.map((municipality) => municipality.id)).size !== data.length
    || new Set(data.map((municipality) => municipality.ibge_code)).size !== data.length) {
    throw new Error('A API de municípios retornou dados inválidos.')
  }

  return data
}
