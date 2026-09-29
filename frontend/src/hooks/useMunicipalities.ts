import { useEffect, useState } from 'react'
import { getMunicipalities } from '../services/municipalities'
import type { Municipality } from '../types/municipality'

interface MunicipalityResult {
  stateIbgeCode: number | null
  data: Municipality[]
  loading: boolean
  error: string | null
}

export function useMunicipalities(stateIbgeCode: number | null) {
  const [result, setResult] = useState<MunicipalityResult>({
    stateIbgeCode, data: [], loading: stateIbgeCode !== null, error: null,
  })

  if (result.stateIbgeCode !== stateIbgeCode) {
    setResult({ stateIbgeCode, data: [], loading: stateIbgeCode !== null, error: null })
  }

  useEffect(() => {
    if (stateIbgeCode === null) return

    const code = stateIbgeCode
    const controller = new AbortController()

    async function loadMunicipalities() {
      try {
        const data = await getMunicipalities(code, controller.signal)
        if (!controller.signal.aborted) {
          setResult({ stateIbgeCode, data, loading: false, error: null })
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setResult({
            stateIbgeCode, data: [], loading: false,
            error: error instanceof Error ? error.message : 'Não foi possível carregar os municípios.',
          })
        }
      }
    }

    void loadMunicipalities()
    return () => controller.abort()
  }, [stateIbgeCode])

  if (stateIbgeCode === null || result.stateIbgeCode !== stateIbgeCode) {
    return { data: [], loading: stateIbgeCode !== null, error: null }
  }

  return { data: result.data, loading: result.loading, error: result.error }
}
