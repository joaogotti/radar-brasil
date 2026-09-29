import { Link } from 'react-router'
import type { MunicipalitySearchPage } from '../../types/municipality'

interface MunicipalityResultsProps {
  data: MunicipalitySearchPage | null
  loading: boolean
  error: string | null
  onPageChange: (page: number) => void
  onRetry: () => void
}

const buttonClass = 'min-h-11 rounded-md border border-border bg-surface px-4 py-2 text-sm font-medium text-primary hover:bg-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-default disabled:text-muted disabled:opacity-50'

function MunicipalityResults({ data, loading, error, onPageChange, onRetry }: MunicipalityResultsProps) {
  return (
    <div className="mt-8">
      <p role="status" className="text-sm font-medium text-foreground/80">
        {loading ? 'Carregando municípios…' : data ? `${data.total.toLocaleString('pt-BR')} ${data.total === 1 ? 'município encontrado' : 'municípios encontrados'}` : ''}
      </p>
      <div aria-busy={loading} className="mt-3 min-h-40 rounded-panel border border-border bg-surface">
        {error && (
          <div className="p-6">
            <p role="alert" className="text-sm text-muted">{error}</p>
            <button type="button" className={`${buttonClass} mt-4`} onClick={onRetry}>Tentar novamente</button>
          </div>
        )}
        {!loading && data && data.total === 0 && (
          <div className="p-6">
            <h2 className="text-base">Nenhum município encontrado.</h2>
            <p className="mt-2 text-sm text-muted">Revise o nome ou altere o filtro de UF.</p>
          </div>
        )}
        {!loading && data && data.data.length > 0 && (
          <ul aria-label="Municípios encontrados" className="divide-y divide-border">
            {data.data.map((municipality) => (
              <li key={municipality.ibge_code} className="first:rounded-t-panel last:rounded-b-panel">
                <Link to={`/municipios/${municipality.ibge_code}`} className="flex flex-col gap-2 rounded-[inherit] px-5 py-4 hover:bg-background/60 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary sm:grid sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] sm:items-center sm:gap-6">
                  <div className="min-w-0">
                    <h2 className="break-words text-base">{municipality.name}</h2>
                    <p className="mt-1 text-sm text-muted">{municipality.state.name} • {municipality.state.abbreviation}</p>
                  </div>
                  <p className="shrink-0 text-xs text-muted">Código IBGE <span className="tabular-nums">{municipality.ibge_code}</span></p>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>
      {data && data.total > 0 && (
        <nav aria-label="Paginação dos municípios" className="mt-5 flex flex-wrap items-center justify-between gap-3">
          <button type="button" className={buttonClass} disabled={loading || data.current_page <= 1} onClick={() => onPageChange(data.current_page - 1)}>Anterior</button>
          <span className="text-sm text-muted">Página {data.current_page} de {data.last_page}</span>
          <button type="button" className={buttonClass} disabled={loading || data.current_page >= data.last_page} onClick={() => onPageChange(data.current_page + 1)}>Próxima</button>
        </nav>
      )}
    </div>
  )
}

export default MunicipalityResults
