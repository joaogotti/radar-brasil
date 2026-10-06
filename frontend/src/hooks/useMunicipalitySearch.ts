import { useEffect, useState } from 'react'
import { searchMunicipalities } from '../services/municipalitySearch'
import type { MunicipalitySearchFilters, MunicipalitySearchPage } from '../types/municipality'

interface SearchResult {
  key: string
  data: MunicipalitySearchPage | null
  error: string | null
}

export function useMunicipalitySearch({ search, state, page, perPage = 20 }: MunicipalitySearchFilters) {
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState<SearchResult | null>(null)
  const key = JSON.stringify([search, state, page, perPage, attempt])

  useEffect(() => {
    const controller = new AbortController()
    const timer = setTimeout(async () => {
      try {
        const data = await searchMunicipalities({ search, state, page, perPage }, controller.signal)
        if (!controller.signal.aborted) setResult({ key, data, error: null })
      } catch (error) {
        if (!controller.signal.aborted) {
          setResult({ key, data: null, error: error instanceof Error ? error.message : 'Não foi possível carregar os municípios.' })
        }
      }
    }, 350)

    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [search, state, page, perPage, key])

  const current = result?.key === key ? result : null
  return {
    data: current === null ? result?.data ?? null : current.data,
    error: current?.error ?? null,
    loading: current === null,
    retry: () => setAttempt((value) => value + 1),
  }
}
