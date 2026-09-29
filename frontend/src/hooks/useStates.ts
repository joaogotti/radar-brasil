import { useEffect, useState } from 'react'
import { getStates } from '../services/states'
import type { State } from '../types/state'

export function useStates() {
  const [states, setStates] = useState<State[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    async function loadStates() {
      try {
        const data = await getStates(controller.signal)
        if (!controller.signal.aborted) setStates(data)
      } catch (error) {
        if (!controller.signal.aborted) {
          setError(error instanceof Error ? error.message : 'Não foi possível carregar os estados.')
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }

    void loadStates()
    return () => controller.abort()
  }, [])

  return { states, loading, error }
}
