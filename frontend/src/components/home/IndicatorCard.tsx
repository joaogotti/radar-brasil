import type { Indicator } from '../../types/indicator'
import { formatIndicator, type OverviewIndicatorCode } from '../../utils/formatIndicator'

interface IndicatorCardProps {
  code: OverviewIndicatorCode
  label: string
  unit: string
  indicator?: Indicator
  loading: boolean
}

function IndicatorCard({ code, label, unit, indicator, loading }: IndicatorCardProps) {
  return (
    <article className="min-w-0 rounded-panel border border-border bg-surface px-5 py-5 shadow-surface sm:px-6">
      <h3 className="text-xs font-medium tracking-wide text-muted uppercase">{label}</h3>
      {loading ? (
        <div aria-hidden="true" className="mt-3 space-y-2">
          <div className="h-9 w-3/4 rounded bg-border/60" />
          <div className="h-4 w-24 rounded bg-border/40" />
        </div>
      ) : indicator ? (
        <>
          <p className="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-navigation">
            <span className="text-3xl font-semibold tracking-tight tabular-nums">{formatIndicator(code, indicator.value)}</span>
            {unit && <span className="text-sm text-muted">{unit}</span>}
          </p>
          <p className="mt-2 text-xs text-muted">Censo {indicator.reference_year}</p>
        </>
      ) : <p className="mt-5 text-sm text-muted">Dado não disponível.</p>}
    </article>
  )
}

export default IndicatorCard
