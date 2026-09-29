import { useState } from 'react'
import { useMunicipalities } from '../../hooks/useMunicipalities'
import { useMunicipalityIndicators } from '../../hooks/useMunicipalityIndicators'
import { useStates } from '../../hooks/useStates'
import BrazilStatesMap from '../maps/BrazilStatesMap'
import MunicipalitySelection from './MunicipalitySelection'
import MunicipalityOverview from './MunicipalityOverview'
import MunicipalityComparison from '../comparison/MunicipalityComparison'

function Hero() {
  const [selectedStateIbgeCode, setSelectedStateIbgeCode] = useState<number | null>(null)
  const [selectedMunicipalityIbgeCode, setSelectedMunicipalityIbgeCode] = useState<number | null>(null)
  const { states, loading: statesLoading, error: statesError } = useStates()
  const { data: municipalities, loading, error } = useMunicipalities(selectedStateIbgeCode)
  const { data: indicatorData, loading: indicatorsLoading, error: indicatorsError } = useMunicipalityIndicators(selectedMunicipalityIbgeCode)
  const municipalityStatus = selectedStateIbgeCode === null ? null
    : loading ? 'Carregando municípios…'
      : error ?? `${municipalities.length} ${municipalities.length === 1 ? 'município' : 'municípios'}`

  function selectState(ibgeCode: number | null) {
    if (ibgeCode !== selectedStateIbgeCode) {
      setSelectedStateIbgeCode(ibgeCode)
      setSelectedMunicipalityIbgeCode(null)
    }
  }

  return (
    <>
      <section
        aria-labelledby="hero-title"
        className="bg-navigation text-surface"
      >
        <div className="mx-auto grid max-w-7xl items-center gap-8 px-6 py-10 sm:py-12 lg:grid-cols-[1fr_1.15fr] lg:gap-10 lg:px-8 lg:py-8">
          <div>
            <p className="text-xs font-semibold tracking-widest text-surface/60">
              DADOS PÚBLICOS • BRASIL
            </p>
            <h1
              id="hero-title"
              className="mt-4 max-w-xl text-4xl leading-tight tracking-tight text-balance sm:text-5xl"
            >
              Dados do <span className="text-primary">Brasil</span>, de forma mais clara.
            </h1>
            <p className="mt-5 max-w-lg text-base leading-relaxed text-surface/70 sm:text-lg">
              Explore indicadores econômicos, sociais e demográficos de estados e
              municípios brasileiros em uma única plataforma.
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
              <button
                type="button"
                className="min-h-11 rounded-md bg-primary px-5 py-3 text-sm font-semibold text-surface transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface motion-reduce:transition-none"
              >
                Explorar indicadores
              </button>
              <a
                href="#comparar-municipios"
                className="min-h-11 rounded-md px-5 py-3 text-center text-sm font-medium text-surface/80 transition-colors hover:bg-surface/5 hover:text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface motion-reduce:transition-none"
              >
                Comparar municípios
              </a>
            </div>
          </div>

          <div className="min-w-0">
            <BrazilStatesMap
              selectedStateIbgeCode={selectedStateIbgeCode}
              onStateSelect={selectState}
              municipalityStatus={municipalityStatus}
            />
            {selectedStateIbgeCode !== null && !loading && !error && municipalities.length > 0 && (
              <MunicipalitySelection
                municipalities={municipalities}
                selectedIbgeCode={selectedMunicipalityIbgeCode}
                onSelect={setSelectedMunicipalityIbgeCode}
              />
            )}
          </div>
        </div>
      </section>
      <MunicipalityOverview
        municipality={municipalities.find((municipality) => municipality.ibge_code === selectedMunicipalityIbgeCode)}
        stateName={states.find((state) => state.ibge_code === selectedStateIbgeCode)?.name ?? `UF ${selectedStateIbgeCode}`}
        data={indicatorData}
        loading={indicatorsLoading}
        error={indicatorsError}
      />
      <MunicipalityComparison states={states} statesLoading={statesLoading} statesError={statesError} />
    </>
  )
}

export default Hero
