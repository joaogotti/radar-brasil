import { useEffect, useState } from 'react'
import { getMunicipalityIndicators, IndicatorRequestError } from '../services/municipalityIndicators'
import type { MunicipalityIndicators } from '../types/indicator'

interface IndicatorResult {
  key: string
  data: MunicipalityIndicators | null
  error: string | null
  notFound: boolean
}

export function useMunicipalityIndicators(municipalityIbgeCode: number | null) {
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState<IndicatorResult | null>(null)
  const key = JSON.stringify([municipalityIbgeCode, attempt])

  useEffect(() => {
    if (municipalityIbgeCode === null) return

    const code = municipalityIbgeCode
    const controller = new AbortController()

    async function loadIndicators() {
      try {
        const data = await getMunicipalityIndicators(code, controller.signal)
        if (!controller.signal.aborted) {
          setResult({ key, data, error: null, notFound: false })
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setResult({
            key, data: null,
            error: error instanceof Error ? error.message : 'Não foi possível carregar os indicadores.',
            notFound: error instanceof IndicatorRequestError && error.status === 404,
          })
        }
      }
    }

    void loadIndicators()
    return () => controller.abort()
  }, [municipalityIbgeCode, key])

  const current = municipalityIbgeCode !== null && result?.key === key ? result : null
  return {
    data: current?.data ?? null,
    loading: municipalityIbgeCode !== null && current === null,
    error: current?.error ?? null,
    notFound: current?.notFound ?? false,
    retry: () => setAttempt((value) => value + 1),
  }
}
