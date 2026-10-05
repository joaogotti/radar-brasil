import { useEffect, useId, useRef, useState } from 'react'
import { useMunicipalitySearch } from '../../hooks/useMunicipalitySearch'
import { useStates } from '../../hooks/useStates'
import type { MunicipalitySearchFilters } from '../../types/municipality'
import { hasMunicipalitySearchCriteria } from '../../utils/indicatorExplorer'

const buttonClass = 'min-h-11 rounded-md border border-border px-4 py-2 text-sm font-medium text-primary hover:bg-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-default disabled:text-muted disabled:opacity-60'
const inputClass = 'mt-2 min-h-11 w-full min-w-0 rounded-md border border-border bg-surface px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary'

function IndicatorMunicipalityPicker({ onSelect, onClose }: { onSelect: (code: number) => void, onClose: () => void }) {
  const id = useId()
  const input = useRef<HTMLInputElement>(null)
  const [filters, setFilters] = useState<MunicipalitySearchFilters>({ search: '', state: null, page: 1 })
  const states = useStates()

  useEffect(() => { input.current?.focus() }, [])

  return (
    <fieldset className="mt-5 min-w-0" onKeyDown={(event) => { if (event.key === 'Escape') { event.preventDefault(); onClose() } }}>
      <legend className="sr-only">Busca municipal</legend>
      <div className="grid gap-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div className="min-w-0"><label htmlFor={`${id}-search`} className="text-sm font-medium">Buscar município</label>
          <input ref={input} id={`${id}-search`} type="search" maxLength={100} placeholder="Digite o nome do município" className={inputClass} value={filters.search} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} />
        </div>
        <div className="min-w-0"><label htmlFor={`${id}-state`} className="text-sm font-medium">UF (opcional)</label>
          <select id={`${id}-state`} className={inputClass} disabled={states.loading || !!states.error} value={filters.state ?? ''} onChange={(event) => setFilters((current) => ({ ...current, state: event.target.value ? Number(event.target.value) : null, page: 1 }))}>
            <option value="">Todas as UFs</option>
            {states.states.map((state) => <option key={state.ibge_code} value={state.ibge_code}>{state.name} ({state.abbreviation})</option>)}
          </select>
          {(states.loading || states.error) && <p role={states.error ? 'alert' : 'status'} className="mt-2 text-xs text-muted">{states.error ? 'Filtro de UF indisponível. A busca por nome continua disponível; reabra o seletor para tentar carregar as UFs novamente.' : 'Carregando UFs…'}</p>}
        </div>
      </div>
      {hasMunicipalitySearchCriteria(filters.search, filters.state)
        ? <MunicipalitySearchResults filters={filters} onPageChange={page => setFilters(current => ({ ...current, page }))} onSelect={onSelect} />
        : <p role="status" className="mt-5 text-sm leading-relaxed text-muted">Digite o nome do município ou selecione uma UF para começar.</p>}
      <button type="button" onClick={onClose} className={`${buttonClass} mt-5`}>Cancelar</button>
    </fieldset>
  )
}

function MunicipalitySearchResults({ filters, onPageChange, onSelect }: {
  filters: MunicipalitySearchFilters
  onPageChange: (page: number) => void
  onSelect: (code: number) => void
}) {
  const results = useMunicipalitySearch(filters)

  return <div aria-busy={results.loading} className="mt-5">
        <p role="status" className="text-sm text-muted">{results.loading ? 'Carregando municípios…' : results.data ? `${results.data.total.toLocaleString('pt-BR')} municípios encontrados.` : ''}</p>
        {results.error && <div className="mt-3"><p role="alert" className="text-sm text-muted">{results.error}</p><button type="button" className={`${buttonClass} mt-3`} onClick={results.retry}>Tentar novamente</button></div>}
        {!results.loading && results.data && <>
          {results.data.total === 0 ? <p className="mt-3 text-sm text-muted">Nenhum município encontrado. Revise a busca ou o filtro de UF.</p> : <ul aria-label="Resultados da busca municipal" className="mt-3 divide-y divide-border">
            {results.data.data.map((municipality) => <li key={municipality.ibge_code} className="flex min-w-0 flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0"><p className="font-medium break-words text-navigation">{municipality.name} / {municipality.state.abbreviation}</p><p className="mt-1 text-xs text-muted">Código IBGE {municipality.ibge_code}</p></div>
              <button type="button" className={`${buttonClass} shrink-0`} aria-label={`Selecionar ${municipality.name}, ${municipality.state.abbreviation}, código ${municipality.ibge_code}`} onClick={() => onSelect(municipality.ibge_code)}>Selecionar</button>
            </li>)}
          </ul>}
        </>}
        {results.data && results.data.last_page > 1 && <nav aria-label="Paginação da busca" className="mt-4 flex flex-wrap items-center justify-between gap-3">
          <button type="button" className={buttonClass} disabled={results.loading || results.data.current_page <= 1} onClick={() => onPageChange(filters.page - 1)}>Anterior</button>
          <span className="text-sm text-muted">Página {results.data.current_page} de {results.data.last_page}</span>
          <button type="button" className={buttonClass} disabled={results.loading || results.data.current_page >= results.data.last_page} onClick={() => onPageChange(filters.page + 1)}>Próxima</button>
        </nav>}
      </div>
}

export default IndicatorMunicipalityPicker
