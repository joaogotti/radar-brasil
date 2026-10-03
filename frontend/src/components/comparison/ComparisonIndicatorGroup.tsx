import type { Indicator } from '../../types/indicator'
import { differentReferenceYears, type ComparisonGroup } from '../../utils/comparisonIndicators'

export interface ComparisonResultSide {
  name: string
  selected: boolean
  loading: boolean
  error: string | null
  values: Record<string, Indicator | undefined>
}

function ComparisonIndicatorGroup({ group, sides }: { group: ComparisonGroup, sides: [ComparisonResultSide, ComparisonResultSide] }) {
  return (
    <section aria-labelledby={`compare-${group.id}`} className="mt-10">
      <h2 id={`compare-${group.id}`} className="text-lg text-navigation">{group.title}</h2>
      <div className="mt-4 divide-y divide-border rounded-panel border border-border bg-surface shadow-surface">
        <div aria-hidden="true" className="hidden gap-6 px-6 py-4 text-sm font-medium text-navigation md:grid md:grid-cols-3">
          <span>Indicador</span>
          {sides.map((side, index) => <span key={index} className="min-w-0 break-words">{side.name}</span>)}
        </div>
        {group.indicators.map(({ code, label, description, unit, format }) => {
          const years = differentReferenceYears(sides[0].values[code], sides[1].values[code])
          return <div key={code} className="grid gap-4 px-5 py-5 sm:px-6 md:grid-cols-3 md:gap-6">
            <div className="min-w-0">
              <h3 id={`compare-${group.id}-${code}`} className="text-sm font-medium text-navigation">{label}</h3>
              {description && <p className="mt-2 text-xs leading-relaxed text-muted">{description}</p>}
              {years.length > 0 && <p className="mt-2 text-xs leading-relaxed text-muted">Referências diferentes: {years.join(' e ')}</p>}
            </div>
            <dl aria-labelledby={`compare-${group.id}-${code}`} className="grid min-w-0 gap-4 md:col-span-2 md:grid-cols-2 md:gap-6">
              {sides.map((side, index) => {
                const item = side.values[code]
                return <div key={index} aria-busy={side.loading} className="min-w-0">
                  <dt className="text-sm text-muted md:sr-only"><span className="sr-only">Município {index === 0 ? 'A' : 'B'}: </span>{side.name}</dt>
                  <dd className="mt-2 md:mt-0">
                    {!side.selected ? <span className="text-sm text-muted">Selecione um município.</span>
                      : side.loading ? <span className="text-sm text-muted">Carregando…</span>
                        : side.error ? <span className="text-sm text-muted">Não foi possível carregar.</span>
                          : !item ? <span className="text-sm text-muted">Dado não disponível.</span>
                            : <><span className="block text-xl font-semibold break-words text-navigation tabular-nums">{format(item.value)}{unit && <span className="ml-1 text-sm font-normal text-muted">{unit}</span>}</span><span className="mt-1 block text-xs text-muted">Referência: {item.reference_year}</span></>}
                  </dd>
                </div>
              })}
            </dl>
          </div>
        })}
      </div>
      {group.note && <p className="mt-3 text-xs leading-relaxed text-muted">{group.note}</p>}
    </section>
  )
}

export default ComparisonIndicatorGroup
