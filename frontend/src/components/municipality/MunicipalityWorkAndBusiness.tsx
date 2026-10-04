import type { Indicator } from '../../types/indicator'
import { workAndBusinessIndicators } from '../../utils/workAndBusinessIndicators'

function MunicipalityWorkAndBusiness({ indicators, loading }: { indicators: Indicator[], loading: boolean }) {
  const cards = workAndBusinessIndicators(indicators)

  return (
    <section aria-labelledby="municipality-work-title" aria-busy={loading} className="mt-12 border-t border-border/60 pt-10">
      <h2 id="municipality-work-title" className="text-lg text-navigation">Trabalho e empresas</h2>
      <p className="mt-2 text-sm text-muted">Estrutura das organizações e do pessoal ocupado no município.</p>
      {loading && <p role="status" className="mt-3 text-sm text-muted">Carregando indicadores de trabalho e empresas…</p>}
      <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        {cards.map(({ code, label, indicator, format }, index) => (
          <div key={code} className={`flex min-w-0 flex-col rounded-panel border border-border bg-surface px-5 py-5 shadow-surface sm:px-6 ${index < 3 ? 'lg:col-span-2' : 'lg:col-span-3'}`}>
            <dt className="text-xs font-medium tracking-wide text-muted uppercase">{label}</dt>
            <dd className="mt-auto pt-3">
              {loading ? <div aria-hidden="true" className="h-9 w-3/4 rounded bg-border/60" />
                : indicator ? <>
                  <span className="block text-3xl font-semibold tracking-tight break-words text-navigation tabular-nums">{format(indicator.value)}</span>
                  <span className="mt-2 block text-xs text-muted">Referência: {indicator.reference_year}</span>
                </> : <span className="text-sm text-muted">Dado não disponível</span>}
            </dd>
          </div>
        ))}
      </dl>
      <p className="mt-5 max-w-prose text-xs leading-relaxed text-muted">Unidades locais são locais de atuação; organizações atuantes não se limitam a sedes. O pessoal ocupado total inclui sócios e proprietários em atividade. Ocupação e salário se referem às organizações, não ao conjunto dos moradores.</p>
      <p className="mt-2 text-xs text-muted">Fonte: IBGE · CEMPRE</p>
    </section>
  )
}

export default MunicipalityWorkAndBusiness
