import type { Indicator } from '../../types/indicator'
import { idebSeries, latestIdebIndicator, schoolCensusIndicator, schoolCensusIndicators, schoolCensusYear } from '../../utils/educationIndicators'
import { formatEducationCount, formatIdeb } from '../../utils/formatEducationIndicator'
import IdebHistoryChart from './IdebHistoryChart'

function MunicipalityEducation({ indicators, loading }: { indicators: Indicator[], loading: boolean }) {
  return (
    <section aria-labelledby="municipality-education-title" aria-busy={loading} className="mt-12 border-t border-border/60 pt-10">
      <h2 id="municipality-education-title" className="text-lg text-navigation">Educação</h2>
      <p className="mt-2 text-sm text-muted">Educação básica e resultados do IDEB da rede pública.</p>
      {loading && <p role="status" className="mt-3 text-sm text-muted">Carregando indicadores de educação…</p>}
      <h3 className="mt-8 text-base font-medium text-navigation">Censo Escolar · {schoolCensusYear}</h3>
      <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {schoolCensusIndicators.map(({ code, label, description }) => {
          const item = schoolCensusIndicator(indicators, code)
          return <div key={code} className="min-w-0 rounded-panel border border-border bg-surface px-5 py-5 shadow-surface sm:px-6">
            <dt className="text-xs font-medium tracking-wide text-muted uppercase">{label}</dt>
            <dd className="mt-3">
              {loading ? <div aria-hidden="true" className="h-9 w-3/4 rounded bg-border/60" /> : item
                ? <span className="block text-3xl font-semibold tracking-tight break-words text-navigation tabular-nums">{formatEducationCount(item.value)}</span>
                : <span className="text-sm text-muted">Dado não disponível.</span>}
              <span className="mt-3 block text-sm leading-relaxed text-muted">{description}</span>
            </dd>
          </div>
        })}
      </dl>
      <h3 className="mt-8 text-base font-medium text-navigation">IDEB · Rede pública</h3>
      <dl className="mt-5 grid gap-4 sm:grid-cols-2">
        {idebSeries.map(({ code, label }) => {
          const item = latestIdebIndicator(indicators, code)
          return <div key={code} className="min-w-0 rounded-panel border border-border bg-surface px-5 py-5 shadow-surface sm:px-6">
            <dt className="text-xs font-medium tracking-wide text-muted uppercase">{label}</dt>
            <dd className="mt-3">
              {loading ? <div aria-hidden="true" className="h-9 w-3/4 rounded bg-border/60" /> : item ? <>
                <span className="block text-3xl font-semibold tracking-tight text-navigation tabular-nums">{formatIdeb(item.value)}</span>
                <span className="mt-2 block text-xs text-muted">{item.reference_year} • Último resultado disponível</span>
              </> : <span className="text-sm text-muted">Resultado não disponível.</span>}
            </dd>
          </div>
        })}
      </dl>
      <div className="mt-8 min-w-0 rounded-panel border border-border bg-surface p-5 shadow-surface sm:p-6">
        <h3 className="text-base font-medium text-navigation">Evolução do IDEB · Rede pública</h3>
        <div className="mt-5">{loading ? <div aria-hidden="true" className="h-64 rounded bg-border/40" /> : <IdebHistoryChart indicators={indicators} />}</div>
      </div>
      <p className="mt-5 text-xs leading-relaxed text-muted">Fonte: INEP • Censo Escolar {schoolCensusYear} e IDEB. Os períodos disponíveis podem variar entre as etapas.</p>
    </section>
  )
}

export default MunicipalityEducation
