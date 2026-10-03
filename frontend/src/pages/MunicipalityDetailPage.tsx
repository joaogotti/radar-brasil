import { Link, useParams } from 'react-router'
import IndicatorCard from '../components/home/IndicatorCard'
import MunicipalityEconomy from '../components/municipality/MunicipalityEconomy'
import MunicipalityDemography from '../components/municipality/MunicipalityDemography'
import MunicipalityLivingConditions from '../components/municipality/MunicipalityLivingConditions'
import MunicipalityEducation from '../components/municipality/MunicipalityEducation'
import { useMunicipalityIndicators } from '../hooks/useMunicipalityIndicators'
import { overviewIndicators } from '../utils/overviewIndicators'

const linkClass = 'text-sm font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary'

function MunicipalityDetailPage() {
  const { ibgeCode = '' } = useParams()
  const validCode = /^[0-9]{7}$/.test(ibgeCode)
  const { data, loading, error, notFound, retry } = useMunicipalityIndicators(validCode ? Number(ibgeCode) : null)
  const missing = !validCode || notFound
  const municipality = data?.municipality
  const cards = overviewIndicators(data?.indicators)
  const hasData = cards.some((card) => card.indicator)
  const years = [...new Set(cards.flatMap((card) => card.indicator ? [card.indicator.reference_year] : []))].sort((a, b) => b - a)

  return (
    <div className="mx-auto max-w-7xl px-6 py-10 lg:px-8 lg:py-14">
      <nav aria-label="Breadcrumb" className="mb-8">
        <ol className="flex flex-wrap items-center gap-2 text-sm text-muted">
          <li><Link to="/municipios" className={linkClass}>Municípios</Link></li>
          <li aria-hidden="true">/</li>
          <li aria-current="page">{municipality?.name ?? (missing ? 'Não encontrado' : 'Município')}</li>
        </ol>
      </nav>
      <p className="text-xs font-medium tracking-widest text-primary uppercase">Município</p>
      <h1 className="mt-3 text-3xl tracking-tight text-navigation sm:text-4xl">
        {municipality?.name ?? (missing ? 'Município não encontrado' : 'Perfil do município')}
      </h1>
      {municipality && (
        <>
          <p className="mt-3 text-base text-muted">{municipality.state.name} • {municipality.state.abbreviation}</p>
          <p className="mt-2 text-sm text-muted">Código IBGE {municipality.ibge_code}</p>
        </>
      )}
      {missing ? (
        <div className="mt-6">
          <p role="status" className="mb-4 text-sm text-muted">Não encontramos um município com este código. Consulte a lista de municípios.</p>
          <Link to="/municipios" className={linkClass}>Voltar para municípios</Link>
        </div>
      ) : error ? (
        <div className="mt-8 rounded-panel border border-border bg-surface p-6">
          <p role="alert" className="text-sm text-muted">Não foi possível carregar os dados do município. Tente novamente.</p>
          <button type="button" onClick={retry} className="mt-4 min-h-11 rounded-md border border-border px-4 py-2 text-sm font-medium text-primary hover:bg-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">Tentar novamente</button>
        </div>
      ) : (
        <>
          <section aria-labelledby="municipality-overview-title" aria-busy={loading} className="mt-10">
            <h2 id="municipality-overview-title" className="text-lg text-navigation">Visão geral</h2>
            {loading && <p role="status" className="mt-3 text-sm text-muted">Carregando dados do município…</p>}
            {loading || hasData ? (
              <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {cards.map((card) => <IndicatorCard key={card.code} {...card} loading={loading} />)}
              </div>
            ) : <p role="status" className="mt-4 rounded-panel border border-border bg-surface p-6 text-sm text-muted">Os indicadores desta referência ainda não estão disponíveis para este município.</p>}
            {hasData && <p className="mt-5 text-xs text-muted">Fonte: IBGE • Censo {years.join(', ')}</p>}
          </section>
          <MunicipalityEconomy indicators={data?.indicators ?? []} loading={loading} />
          <MunicipalityDemography indicators={data?.indicators ?? []} loading={loading} municipalityIbgeCode={municipality?.ibge_code ?? null} />
          <MunicipalityLivingConditions indicators={data?.indicators ?? []} loading={loading} />
          <MunicipalityEducation indicators={data?.indicators ?? []} loading={loading} />
        </>
      )}
    </div>
  )
}

export default MunicipalityDetailPage
