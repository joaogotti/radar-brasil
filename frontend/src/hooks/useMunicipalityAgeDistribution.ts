import { useEffect, useState } from 'react'
import { getMunicipalityAgeDistribution, AgeDistributionRequestError } from '../services/municipalityAgeDistribution'
import type { AgeDistribution } from '../types/ageDistribution'

interface DistributionResult {
  key: string
  data: AgeDistribution | null
  error: string | null
  notFound: boolean
}

export function useMunicipalityAgeDistribution(municipalityIbgeCode: number | null) {
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState<DistributionResult | null>(null)
  const key = JSON.stringify([municipalityIbgeCode, attempt])

  useEffect(() => {
    if (municipalityIbgeCode === null) return

    const code = municipalityIbgeCode
    const controller = new AbortController()

    async function loadDistribution() {
      try {
        const data = await getMunicipalityAgeDistribution(code, controller.signal)
        if (!controller.signal.aborted) {
          setResult({ key, data, error: null, notFound: false })
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setResult({
            key, data: null,
            error: error instanceof Error ? error.message : 'Não foi possível carregar a distribuição etária.',
            notFound: error instanceof AgeDistributionRequestError && error.status === 404,
          })
        }
      }
    }

    void loadDistribution()
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
