import { useEffect, useState } from 'react'
import { getMunicipalityIndicators } from '../services/municipalityIndicators'
import type { MunicipalityIndicators } from '../types/indicator'

interface IndicatorResult {
  municipalityIbgeCode: number | null
  data: MunicipalityIndicators | null
  loading: boolean
  error: string | null
}

export function useMunicipalityIndicators(municipalityIbgeCode: number | null) {
  const [result, setResult] = useState<IndicatorResult>({
    municipalityIbgeCode, data: null, loading: municipalityIbgeCode !== null, error: null,
  })

  if (result.municipalityIbgeCode !== municipalityIbgeCode) {
    setResult({ municipalityIbgeCode, data: null, loading: municipalityIbgeCode !== null, error: null })
  }

  useEffect(() => {
    if (municipalityIbgeCode === null) return

    const code = municipalityIbgeCode
    const controller = new AbortController()

    async function loadIndicators() {
      try {
        const data = await getMunicipalityIndicators(code, controller.signal)
        if (!controller.signal.aborted) {
          setResult({ municipalityIbgeCode: code, data, loading: false, error: null })
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setResult({
            municipalityIbgeCode: code, data: null, loading: false,
            error: error instanceof Error ? error.message : 'Não foi possível carregar os indicadores.',
          })
        }
      }
    }

    void loadIndicators()
    return () => controller.abort()
  }, [municipalityIbgeCode])

  if (municipalityIbgeCode === null || result.municipalityIbgeCode !== municipalityIbgeCode) {
    return { data: null, loading: municipalityIbgeCode !== null, error: null }
  }

  return { data: result.data, loading: result.loading, error: result.error }
}
