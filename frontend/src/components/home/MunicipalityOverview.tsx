import { useId } from 'react'
import type { Municipality } from '../../types/municipality'
import type { Indicator, MunicipalityIndicators } from '../../types/indicator'
import type { OverviewIndicatorCode } from '../../utils/formatIndicator'
import IndicatorCard from './IndicatorCard'

const cards: { code: OverviewIndicatorCode, label: string, unit: string }[] = [
  { code: 'population_census', label: 'População', unit: '' },
  { code: 'territorial_area', label: 'Área territorial', unit: 'km²' },
  { code: 'population_density', label: 'Densidade demográfica', unit: 'hab./km²' },
]

interface MunicipalityOverviewProps {
  municipality?: Municipality
  stateName: string
  data: MunicipalityIndicators | null
  loading: boolean
  error: string | null
}

function MunicipalityOverview({ municipality, stateName, data, loading, error }: MunicipalityOverviewProps) {
  const id = useId()
  const indicators = cards.map((card) => ({
    ...card,
    indicator: data?.indicators.filter((indicator) => indicator.code === card.code)
      .reduce<Indicator | undefined>((latest, indicator) =>
        !latest || indicator.reference_year > latest.reference_year ? indicator : latest, undefined),
  }))
  const hasData = indicators.some((card) => card.indicator)

  return (
    <section aria-labelledby={id} aria-busy={loading} className="bg-background">
      <div className="mx-auto max-w-7xl px-6 py-8 lg:px-8 lg:py-10">
        <p className="text-xs font-medium tracking-widest text-muted uppercase">Visão geral</p>
        {!municipality ? (
          <>
            <h2 id={id} className="sr-only">Indicadores do município</h2>
            <p className="mt-3 text-sm text-muted">Selecione um município acima para visualizar seus indicadores.</p>
          </>
        ) : (
          <>
            <h2 id={id} className="mt-2 text-3xl tracking-tight text-navigation">{municipality.name}</h2>
            <p className="mt-2 text-sm text-muted">{stateName} • Dados do IBGE</p>
            <div className="mt-5 min-h-36">
              {loading && <p role="status" className="mb-3 text-sm text-muted">Carregando indicadores…</p>}
              {error ? <p role="alert" className="text-sm text-muted">{error}</p>
                : loading || hasData ? (
                  <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {indicators.map((card) => <IndicatorCard key={card.code} {...card} loading={loading} />)}
                  </div>
                ) : <p role="status" className="text-sm text-muted">Não há dados disponíveis do Censo 2022 para este município.</p>}
            </div>
          </>
        )}
      </div>
    </section>
  )
}

export default MunicipalityOverview
