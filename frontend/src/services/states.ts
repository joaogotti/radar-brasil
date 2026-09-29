import type { State } from '../types/state'

function isState(value: unknown): value is State {
  if (typeof value !== 'object' || value === null) return false

  return 'id' in value && Number.isInteger(value.id) && Number(value.id) > 0
    && 'ibge_code' in value && Number.isInteger(value.ibge_code) && Number(value.ibge_code) > 0
    && 'name' in value && typeof value.name === 'string' && value.name.trim().length > 0
    && 'abbreviation' in value && typeof value.abbreviation === 'string' && /^[A-Z]{2}$/.test(value.abbreviation)
}

export async function getStates(signal: AbortSignal): Promise<State[]> {
  const apiUrl = import.meta.env.VITE_API_URL?.trim().replace(/\/+$/, '')
  if (!apiUrl) throw new Error('A URL da API não está configurada.')

  let response: Response
  try {
    response = await fetch(`${apiUrl}/api/states`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.any([signal, AbortSignal.timeout(15000)]),
    })
  } catch (error) {
    if (signal.aborted) throw error
    throw new Error('Não foi possível conectar à API de estados. Tente recarregar a página.')
  }

  if (!response.ok) {
    throw new Error(`Não foi possível carregar os estados (HTTP ${response.status}).`)
  }

  let data: unknown
  try {
    data = await response.json()
  } catch {
    throw new Error('A API de estados retornou uma resposta inválida.')
  }

  if (!Array.isArray(data) || data.length !== 27 || !data.every(isState)
    || new Set(data.map((state) => state.id)).size !== 27
    || new Set(data.map((state) => state.ibge_code)).size !== 27
    || new Set(data.map((state) => state.abbreviation)).size !== 27) {
    throw new Error('A API de estados retornou dados incompletos ou inválidos.')
  }

  return data
}
