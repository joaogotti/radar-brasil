import type { MunicipalitySearchFilters, MunicipalitySearchItem, MunicipalitySearchPage } from '../types/municipality'

function isItem(value: unknown): value is MunicipalitySearchItem {
  if (typeof value !== 'object' || value === null) return false
  if (!('state' in value) || typeof value.state !== 'object' || value.state === null) return false

  return 'id' in value && Number.isInteger(value.id) && Number(value.id) > 0
    && 'ibge_code' in value && Number.isInteger(value.ibge_code) && Number(value.ibge_code) > 0
    && 'name' in value && typeof value.name === 'string' && value.name.trim().length > 0
    && 'ibge_code' in value.state && Number.isInteger(value.state.ibge_code) && Number(value.state.ibge_code) > 0
    && 'name' in value.state && typeof value.state.name === 'string' && value.state.name.trim().length > 0
    && 'abbreviation' in value.state && typeof value.state.abbreviation === 'string' && /^[A-Z]{2}$/.test(value.state.abbreviation)
}

function isPage(value: unknown): value is MunicipalitySearchPage {
  if (typeof value !== 'object' || value === null) return false

  return 'data' in value && Array.isArray(value.data) && value.data.every(isItem)
    && 'current_page' in value && Number.isInteger(value.current_page) && Number(value.current_page) > 0
    && 'last_page' in value && Number.isInteger(value.last_page) && Number(value.last_page) > 0
    && 'per_page' in value && value.per_page === 20 && value.data.length <= value.per_page
    && 'total' in value && Number.isInteger(value.total) && Number(value.total) >= value.data.length
    && value.last_page === Math.max(1, Math.ceil(Number(value.total) / value.per_page))
    && new Set(value.data.map((item) => item.ibge_code)).size === value.data.length
}

export async function searchMunicipalities(filters: MunicipalitySearchFilters, signal: AbortSignal): Promise<MunicipalitySearchPage> {
  const apiUrl = import.meta.env.VITE_API_URL?.trim().replace(/\/+$/, '')
  if (!apiUrl) throw new Error('A URL da API não está configurada.')

  const parameters = new URLSearchParams({ page: String(filters.page), per_page: '20' })
  if (filters.search.trim()) parameters.set('search', filters.search.trim())
  if (filters.state !== null) parameters.set('state', String(filters.state))

  let response: Response
  try {
    response = await fetch(`${apiUrl}/api/municipalities?${parameters}`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.any([signal, AbortSignal.timeout(15000)]),
    })
  } catch (error) {
    if (signal.aborted) throw error
    throw new Error('Não foi possível conectar à API de municípios. Tente novamente.')
  }

  if (!response.ok) throw new Error('Não foi possível carregar os municípios. Tente novamente.')

  let data: unknown
  try {
    data = await response.json()
  } catch {
    throw new Error('A API de municípios retornou uma resposta inválida. Tente novamente.')
  }

  if (!isPage(data) || data.current_page !== filters.page) {
    throw new Error('A API de municípios retornou dados inválidos. Tente novamente.')
  }

  return data
}
