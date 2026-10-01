import type { Indicator } from '../../types/indicator'
import { useMunicipalityAgeDistribution } from '../../hooks/useMunicipalityAgeDistribution'
import { censusIndicator, demographicIndicators } from '../../utils/demographicIndicators'
import { formatAge, formatRatio } from '../../utils/formatDemographicIndicator'
import AgeDistributionChart from './AgeDistributionChart'

function MunicipalityDemography({ indicators, loading, municipalityIbgeCode }: { indicators: Indicator[], loading: boolean, municipalityIbgeCode: number | null }) {
  const distribution = useMunicipalityAgeDistribution(municipalityIbgeCode)
  return (
    <section aria-labelledby="municipality-demography-title" className="mt-12 border-t border-border/60 pt-10">
      <h2 id="municipality-demography-title" className="text-lg text-navigation">Demografia</h2>
      <p className="mt-2 text-sm text-muted">Idade e composição da população residente.</p>
      {loading && <p role="status" className="mt-3 text-sm text-muted">Carregando indicadores demográficos…</p>}
      <dl aria-busy={loading} className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {demographicIndicators.map(({ code, label, description }) => {
          const indicator = censusIndicator(indicators, code)
          return <div key={code} className="min-w-0 rounded-panel border border-border bg-surface px-5 py-5 shadow-surface sm:px-6">
            <dt className="text-xs font-medium tracking-wide text-muted uppercase">{label}</dt>
            <dd className="mt-3">
              {loading ? <div aria-hidden="true" className="h-9 w-3/4 rounded bg-border/60" />
                : indicator ? <span className="block text-3xl font-semibold tracking-tight break-words text-navigation tabular-nums">{code === 'median_age' ? formatAge(indicator.value) : formatRatio(indicator.value)}</span>
                  : <span className="text-sm text-muted">Dado não disponível.</span>}
              <span className="mt-3 block text-sm leading-relaxed text-muted">{description}</span>
            </dd>
          </div>
        })}
      </dl>
      <div aria-busy={loading || distribution.loading} className="mt-8 min-w-0 rounded-panel border border-border bg-surface p-5 shadow-surface sm:p-6">
        <h3 className="text-base font-medium text-navigation">Pirâmide etária</h3>
        <p className="mt-2 text-sm text-muted">Percentual da população total do município, por idade e sexo.</p>
        {loading || distribution.loading ? <div className="mt-5"><p role="status" className="text-sm text-muted">Carregando distribuição etária…</p><div aria-hidden="true" className="mt-4 h-48 rounded bg-border/40" /></div>
          : distribution.error ? <div className="mt-5">
            <p role="alert" className="text-sm text-muted">Não foi possível carregar a distribuição etária.</p>
            <button type="button" onClick={distribution.retry} className="mt-4 min-h-11 rounded-md border border-border px-4 py-2 text-sm font-medium text-primary hover:bg-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">Tentar novamente</button>
          </div>
            : distribution.data?.availability === 'unavailable' ? <p role="status" className="mt-5 text-sm text-muted">Dados não disponíveis no Censo 2022 para este município.</p>
              : !distribution.data || distribution.data.availability === 'not_loaded' ? <p role="status" className="mt-5 text-sm text-muted">A distribuição etária ainda não está disponível para este município.</p>
                : <div className="mt-5">
                  {distribution.data.availability === 'partial' && <p role="status" className="mb-4 text-sm text-muted">Alguns dados não estão disponíveis.</p>}
                  <AgeDistributionChart key={municipalityIbgeCode} distribution={distribution.data} />
                </div>}
      </div>
      <p className="mt-5 text-xs text-muted">Fonte: IBGE • Censo Demográfico 2022.</p>
    </section>
  )
}

export default MunicipalityDemography
