import type { MunicipalityIndicators } from '../../types/indicator'
import { formatIndicator, type OverviewIndicatorCode } from '../../utils/formatIndicator'

export interface ComparisonSide {
  name: string
  data: MunicipalityIndicators | null
  loading: boolean
  error: string | null
  selected: boolean
}

interface ComparisonRowProps {
  code: OverviewIndicatorCode
  label: string
  unit: string
  sides: [ComparisonSide, ComparisonSide]
}

function ComparisonRow({ code, label, unit, sides }: ComparisonRowProps) {
  return (
    <article className="rounded-panel border border-border bg-surface px-5 py-4 sm:px-6">
      <h3 className="text-xs font-medium tracking-wide text-muted uppercase">{label}</h3>
      <div className="mt-3 grid divide-y divide-border/60 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
        {sides.map((side, index) => {
          const indicator = side.data?.indicators.find((item) => item.code === code && item.reference_year === 2022)
          return (
            <div key={index} className="min-w-0 first:pb-4 last:pt-4 sm:first:pr-6 sm:first:pb-0 sm:last:pt-0 sm:last:pl-6" aria-busy={side.loading}>
              <p className="flex items-baseline gap-2 text-sm font-medium text-navigation">
                <span className="text-xs font-normal text-muted" aria-label={`Município ${index === 0 ? 'A' : 'B'}`}>{index === 0 ? 'A' : 'B'}</span>
                {side.name}
              </p>
              <div className="mt-2 min-h-9">
                {!side.selected ? <p className="text-sm text-muted">Selecione um município.</p>
                  : side.loading ? <p className="text-sm text-muted">Carregando…</p>
                    : side.error ? <p className="text-sm text-muted">Não foi possível carregar.</p>
                      : !indicator ? <p className="text-sm text-muted">Não disponível para 2022.</p>
                        : <p className="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-navigation">
                          <span className="text-3xl font-semibold tracking-tight tabular-nums">{formatIndicator(code, indicator.value)}</span>
                          {unit && <span className="text-sm text-muted">{unit}</span>}
                        </p>}
              </div>
            </div>
          )
        })}
      </div>
    </article>
  )
}

export default ComparisonRow
