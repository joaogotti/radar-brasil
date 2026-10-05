import { Link } from 'react-router'
import type { MunicipalityIndicators } from '../../types/indicator'
import type { CatalogIndicator } from '../../utils/indicatorCatalog'
import { indicatorObservations } from '../../utils/indicatorExplorer'

interface Props {
  indicator: CatalogIndicator
  municipalityCode: number | null
  data: MunicipalityIndicators | null
  loading: boolean
  error: string | null
  notFound: boolean
  retry: () => void
}

function IndicatorExplorer({ indicator, municipalityCode, data, loading, error, notFound, retry }: Props) {
  const history = indicatorObservations(data?.indicators ?? [], indicator.code)
  const latest = history[0]
  const linkClass = 'text-sm font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary'

  return <div aria-busy={loading} className="mt-5 min-w-0">
    {municipalityCode === null ? <p className="text-sm text-muted">Escolha um município para consultar este indicador.</p>
      : loading ? <p role="status" className="text-sm text-muted">Carregando dados do município…</p>
        : notFound ? <p role="status" className="text-sm text-muted">Município não encontrado. Escolha outro município para continuar.</p>
          : error ? <div><p role="alert" className="text-sm text-muted">Não foi possível carregar os dados do município.</p><button type="button" onClick={retry} className={`${linkClass} mt-3 min-h-11`}>Tentar novamente</button></div>
            : data && <>
              <h3 className="text-base font-medium break-words text-navigation">{data.municipality.name} / {data.municipality.state.abbreviation}</h3>
              {latest ? <>
                <p className="mt-3 text-3xl font-semibold break-words text-navigation tabular-nums">{indicator.format(latest.value)}</p>
                <p className="mt-2 text-sm text-muted">Referência: {latest.reference_year} · Último resultado disponível</p>
              </> : <p role="status" className="mt-3 text-sm text-muted">Dado não disponível</p>}
              {history.length > 1 && <details key={`${indicator.code}:${municipalityCode}`} className="mt-6 border-t border-border pt-4">
                <summary className="min-h-11 cursor-pointer text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">Ver histórico ({history.length} referências)</summary>
                <table className="mt-3 w-full table-fixed text-left text-sm tabular-nums">
                  <caption className="mb-3 text-left text-xs leading-relaxed text-muted">{indicator.name} — {data.municipality.name} / {data.municipality.state.abbreviation}. Somente referências disponíveis; lacunas não são preenchidas. Unidade: {indicator.unit}.</caption>
                  <thead><tr className="border-b border-border"><th scope="col" className="w-1/3 py-3 pr-3">Referência</th><th scope="col" className="py-3">Valor</th></tr></thead>
                  <tbody>{history.map(item => <tr key={item.reference_year} className="border-b border-border/60"><th scope="row" className="py-3 pr-3 font-normal">{item.reference_year}</th><td className="py-3 break-words">{indicator.format(item.value)}</td></tr>)}</tbody>
                </table>
              </details>}
              <div className="mt-6 flex flex-wrap gap-x-6 gap-y-4">
                <Link className={linkClass} to={`/municipios/${data.municipality.ibge_code}`}>Ver perfil completo do município</Link>
                {indicator.theme !== 'work' && <Link className={linkClass} to={`/comparar?municipios=${data.municipality.ibge_code}`}>Comparar município</Link>}
              </div>
            </>}
  </div>
}

export default IndicatorExplorer
