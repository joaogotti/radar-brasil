import type { Indicator } from '../../types/indicator'
import { censusIndicator, livingConditionIndicators } from '../../utils/demographicIndicators'
import { formatPercentage } from '../../utils/formatDemographicIndicator'

function MunicipalityLivingConditions({ indicators, loading }: { indicators: Indicator[], loading: boolean }) {
  return (
    <section aria-labelledby="municipality-living-title" aria-busy={loading} className="mt-12 border-t border-border/60 pt-10">
      <h2 id="municipality-living-title" className="text-lg text-navigation">Condições de vida</h2>
      <p className="mt-2 text-sm text-muted">Alfabetização e serviços nos domicílios.</p>
      {loading && <p role="status" className="mt-3 text-sm text-muted">Carregando indicadores de condições de vida…</p>}
      <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {livingConditionIndicators.map(({ code, label, description }) => {
          const indicator = censusIndicator(indicators, code)
          return <div key={code} className="min-w-0 rounded-panel border border-border bg-surface px-5 py-5 shadow-surface sm:px-6">
            <dt className="text-xs font-medium tracking-wide text-muted uppercase">{label}</dt>
            <dd className="mt-3">
              {loading ? <div aria-hidden="true" className="h-9 w-3/4 rounded bg-border/60" />
                : indicator ? <span className="block text-3xl font-semibold tracking-tight break-words text-navigation tabular-nums">{formatPercentage(indicator.value)}</span>
                  : <span className="text-sm text-muted">Dado não disponível.</span>}
              <span className="mt-3 block text-sm leading-relaxed text-muted">{description}</span>
            </dd>
          </div>
        })}
      </dl>
      <p className="mt-5 text-xs leading-relaxed text-muted">Os indicadores domiciliares consideram domicílios particulares permanentes ocupados (DPPO).</p>
      <p className="mt-2 text-xs text-muted">Fonte: IBGE • Censo Demográfico 2022.</p>
    </section>
  )
}

export default MunicipalityLivingConditions
