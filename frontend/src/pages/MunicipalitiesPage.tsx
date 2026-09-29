import { useState } from 'react'
import MunicipalityResults from '../components/municipalities/MunicipalityResults'
import { useMunicipalitySearch } from '../hooks/useMunicipalitySearch'
import { useStates } from '../hooks/useStates'
import type { MunicipalitySearchFilters } from '../types/municipality'

const inputClass = 'mt-2 min-h-11 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:text-muted'

function MunicipalitiesPage() {
  const [filters, setFilters] = useState<MunicipalitySearchFilters>({ search: '', state: null, page: 1 })
  const states = useStates()
  const results = useMunicipalitySearch(filters)

  return (
    <section aria-labelledby="municipalities-title" className="mx-auto max-w-7xl px-6 py-10 lg:px-8 lg:py-14">
      <p className="text-xs font-medium tracking-widest text-primary uppercase">Municípios</p>
      <h1 id="municipalities-title" className="mt-3 max-w-3xl text-3xl tracking-tight text-navigation sm:text-4xl">
        Explore os municípios do Brasil
      </h1>
      <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted">
        Conheça os dados demográficos e territoriais dos municípios brasileiros com informações oficiais do IBGE.
      </p>
      <div role="search" aria-label="Explorar municípios" className="mt-8 grid gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,18rem)] lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">
        <div>
          <label htmlFor="municipality-search" className="text-sm font-medium">Buscar município</label>
          <input
            id="municipality-search"
            type="search"
            maxLength={100}
            placeholder="Digite o nome de um município"
            className={inputClass}
            value={filters.search}
            onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value, page: 1 }))}
          />
        </div>
        <div>
          <label htmlFor="municipality-state" className="text-sm font-medium">UF</label>
          <select
            id="municipality-state"
            className={inputClass}
            value={filters.state ?? ''}
            disabled={states.loading || states.error !== null}
            aria-describedby={states.loading || states.error ? 'municipality-state-status' : undefined}
            onChange={(event) => setFilters((current) => ({ ...current, state: event.target.value ? Number(event.target.value) : null, page: 1 }))}
          >
            <option value="">Todas as UFs</option>
            {states.states.map((state) => <option key={state.ibge_code} value={state.ibge_code}>{state.name} ({state.abbreviation})</option>)}
          </select>
          {(states.loading || states.error) && (
            <p id="municipality-state-status" role={states.error ? 'alert' : 'status'} className="mt-2 text-xs text-muted">
              {states.error ? 'Não foi possível carregar as UFs. Recarregue a página para tentar novamente.' : 'Carregando UFs…'}
            </p>
          )}
        </div>
      </div>
      <MunicipalityResults
        {...results}
        onRetry={results.retry}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
      />
    </section>
  )
}

export default MunicipalitiesPage
