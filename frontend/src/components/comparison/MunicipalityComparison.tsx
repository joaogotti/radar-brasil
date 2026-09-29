import { useState } from 'react'
import { useMunicipalities } from '../../hooks/useMunicipalities'
import { useMunicipalityIndicators } from '../../hooks/useMunicipalityIndicators'
import type { State } from '../../types/state'
import type { OverviewIndicatorCode } from '../../utils/formatIndicator'
import ComparisonSelector from './ComparisonSelector'
import ComparisonRow, { type ComparisonSide } from './ComparisonRow'

interface Selection {
  stateCode: number | null
  municipalityCode: number | null
}

interface MunicipalityComparisonProps {
  states: State[]
  statesLoading: boolean
  statesError: string | null
}

const indicators: { code: OverviewIndicatorCode, label: string, unit: string }[] = [
  { code: 'population_census', label: 'População', unit: '' },
  { code: 'territorial_area', label: 'Área territorial', unit: 'km²' },
  { code: 'population_density', label: 'Densidade demográfica', unit: 'hab./km²' },
]

function MunicipalityComparison({ states, statesLoading, statesError }: MunicipalityComparisonProps) {
  const [selectionA, setSelectionA] = useState<Selection>({ stateCode: null, municipalityCode: null })
  const [selectionB, setSelectionB] = useState<Selection>({ stateCode: null, municipalityCode: null })
  const municipalitiesA = useMunicipalities(selectionA.stateCode)
  const municipalitiesB = useMunicipalities(selectionB.stateCode)
  const sameMunicipality = selectionA.municipalityCode !== null && selectionA.municipalityCode === selectionB.municipalityCode
  const indicatorsA = useMunicipalityIndicators(selectionA.municipalityCode)
  const indicatorsB = useMunicipalityIndicators(sameMunicipality ? null : selectionB.municipalityCode)
  const selections = [
    { selection: selectionA, setSelection: setSelectionA, municipalities: municipalitiesA, indicators: indicatorsA, label: 'Município A' },
    { selection: selectionB, setSelection: setSelectionB, municipalities: municipalitiesB, indicators: indicatorsB, label: 'Município B' },
  ]
  const sides = selections.map(({ selection, municipalities, indicators: result, label }): ComparisonSide => {
    const municipality = municipalities.data.find((item) => item.ibge_code === selection.municipalityCode)
    const state = states.find((item) => item.ibge_code === selection.stateCode)
    return {
      name: municipality ? `${municipality.name} / ${state?.abbreviation ?? selection.stateCode}` : label,
      data: result.data, loading: result.loading, error: result.error, selected: selection.municipalityCode !== null,
    }
  })

  return (
    <section id="comparar-municipios" tabIndex={-1} aria-labelledby="comparison-title"
      className="scroll-mt-6 border-t border-border/60 bg-background focus-visible:outline-2 focus-visible:outline-primary">
      <div className="mx-auto max-w-7xl px-6 py-8 lg:px-8 lg:py-10">
        <h2 id="comparison-title" className="text-2xl tracking-tight text-navigation">Compare municípios</h2>
        <p className="mt-2 text-sm text-muted">População, área territorial e densidade demográfica. Fonte: IBGE • Censo 2022.</p>
        <div className="mt-5 grid gap-4 md:grid-cols-2">
          {selections.map(({ selection, setSelection, municipalities, indicators: result, label }) => (
            <ComparisonSelector key={label} label={label} states={states} statesLoading={statesLoading}
              stateCode={selection.stateCode} municipalityCode={selection.municipalityCode}
              municipalities={municipalities.data} municipalitiesLoading={municipalities.loading}
              error={statesError ?? municipalities.error ?? result.error}
              indicatorStatus={result.loading ? 'Carregando indicadores…'
                : result.data && !result.data.indicators.some((item) => item.reference_year === 2022)
                  ? 'Não há indicadores disponíveis para 2022.' : null}
              onStateChange={(stateCode) => setSelection((previous) => previous.stateCode === stateCode ? previous : { stateCode, municipalityCode: null })}
              onMunicipalityChange={(municipalityCode) => setSelection((previous) => ({ ...previous, municipalityCode }))}
            />
          ))}
        </div>
        <div className="mt-5">
          {sameMunicipality ? <p role="status" className="text-sm text-muted">Escolha municípios diferentes para comparar.</p>
            : sides.some((side) => side.selected) ? <div className="space-y-3">
              {indicators.map((indicator) => <ComparisonRow key={indicator.code} {...indicator} sides={[sides[0], sides[1]]} />)}
            </div>
              : <p className="text-sm text-muted">Selecione uma UF e um município em cada lado para começar.</p>}
        </div>
      </div>
    </section>
  )
}

export default MunicipalityComparison
