import { geoMercator, geoPath } from 'd3-geo'
import { useId, useState } from 'react'
import statesData from '../../assets/geography/brazil-states.json'
import { useStates } from '../../hooks/useStates'
import type { BrazilStatesCollection } from '../../types/geography'

const geography = statesData as BrazilStatesCollection
const projection = geoMercator().fitExtent([[20, 20], [500, 460]], geography)
const createPath = geoPath(projection)
const pathsByCode = new Map(geography.features.map((feature) => [
  feature.properties.ibgeCode,
  createPath(feature) ?? '',
]))

interface BrazilStatesMapProps {
  selectedStateIbgeCode: number | null
  onStateSelect: (stateIbgeCode: number | null) => void
  municipalityStatus: string | null
}

function BrazilStatesMap({ selectedStateIbgeCode, onStateSelect, municipalityStatus }: BrazilStatesMapProps) {
  const id = useId()
  const { states: apiStates, loading, error } = useStates()
  const geometryMismatch = !loading && !error
    && (apiStates.length !== pathsByCode.size
      || apiStates.some((state) => !pathsByCode.has(String(state.ibge_code))))
  const mapError = error ?? (geometryMismatch ? 'Não foi possível associar os estados ao mapa.' : null)
  const states = mapError ? [] : apiStates.map((state) => ({
    ...state,
    ibgeCode: String(state.ibge_code),
    path: pathsByCode.get(String(state.ibge_code)) ?? '',
  }))
  const [hoveredCode, setHoveredCode] = useState<string | null>(null)
  const [focusedCode, setFocusedCode] = useState<string | null>(null)
  const selectedCode = selectedStateIbgeCode === null ? '' : String(selectedStateIbgeCode)
  const activeCode = hoveredCode ?? focusedCode ?? selectedCode
  const selectedState = states.find((state) => state.ibgeCode === selectedCode)

  return (
    <figure className="relative min-w-0 text-surface" aria-labelledby={`${id}-title`}>
      <figcaption id={`${id}-title`} className="sr-only">
        Mapa interativo das unidades da Federação do Brasil. Malha geográfica do IBGE.
      </figcaption>

      <p id={`${id}-help`} className="sr-only">
        Selecione uma unidade da Federação pelo mapa.
        No mapa, use Tab para percorrer os estados e Enter ou Espaço para selecionar.
      </p>
      <svg
        viewBox="0 0 520 480"
        className="block h-auto w-full lg:scale-108"
        style={{
          backgroundImage: 'radial-gradient(ellipse at center, color-mix(in srgb, var(--color-primary) 9%, transparent), transparent 68%)',
        }}
        role="group"
        aria-labelledby={`${id}-title`}
        aria-describedby={`${id}-help`}
        aria-busy={loading}
      >
        {(loading || mapError) && (
          <g aria-hidden="true" className="fill-primary/25 stroke-primary/75 stroke-1">
            {[...pathsByCode].map(([code, path]) => (
              <path key={code} d={path} vectorEffect="non-scaling-stroke" strokeLinejoin="round" />
            ))}
          </g>
        )}
        {states.map((state) => (
          <path
            key={state.ibgeCode}
            d={state.path}
            role="button"
            tabIndex={0}
            aria-label={`${state.name} (${state.abbreviation})`}
            aria-pressed={selectedCode === state.ibgeCode}
            onPointerEnter={(event) => {
              if (event.pointerType !== 'touch') setHoveredCode(state.ibgeCode)
            }}
            onPointerLeave={() => setHoveredCode(null)}
            onFocus={() => setFocusedCode(state.ibgeCode)}
            onBlur={() => setFocusedCode(null)}
            onClick={() => onStateSelect(state.ibge_code)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                onStateSelect(state.ibge_code)
              }
            }}
            vectorEffect="non-scaling-stroke"
            strokeLinejoin="round"
            className={`cursor-pointer stroke-primary/75 stroke-1 transition-colors duration-150 focus-visible:stroke-surface focus-visible:stroke-2 focus-visible:outline-none motion-reduce:transition-none ${
              selectedCode === state.ibgeCode
                ? 'fill-primary'
                : activeCode === state.ibgeCode
                  ? 'fill-primary/55'
                  : 'fill-primary/25'
            }`}
          />
        ))}
        {selectedState && (
          <path
            d={selectedState.path}
            aria-hidden="true"
            pointerEvents="none"
            vectorEffect="non-scaling-stroke"
            strokeLinejoin="round"
            className="fill-none stroke-surface/70 stroke-1"
          />
        )}
        {focusedCode && (
          <path
            d={states.find((state) => state.ibgeCode === focusedCode)?.path}
            aria-hidden="true"
            pointerEvents="none"
            vectorEffect="non-scaling-stroke"
            strokeLinejoin="round"
            className="fill-none stroke-surface stroke-2"
          />
        )}
      </svg>

      {(loading || mapError) && (
        <p
          role={mapError ? 'alert' : 'status'}
          className="absolute inset-x-4 top-1/2 -translate-y-1/2 rounded-md bg-navigation/95 px-4 py-3 text-center text-sm text-surface/80"
        >
          {mapError ?? 'Carregando estados…'}
        </p>
      )}

      <div className="grid min-h-16 grid-cols-2 items-start gap-4">
        <div role="status" aria-live="polite" aria-atomic="true">
          {selectedState && (
            <div className="border-l-2 border-primary pl-3">
              <p className="text-lg font-semibold tracking-wide">{selectedState.abbreviation}</p>
              <p className="mt-1 text-sm text-surface/70">{selectedState.name}</p>
              {municipalityStatus && <p className="mt-1 text-xs text-surface/60">{municipalityStatus}</p>}
            </div>
          )}
        </div>
        <details className="hidden">
          <summary className="flex min-h-11 cursor-pointer items-center rounded text-xs text-surface/60 hover:text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface">
            Selecionar por nome
          </summary>
          <div className="absolute right-0 bottom-full z-10 mb-1 w-60 rounded-md border border-surface/15 bg-navigation p-3">
            <label htmlFor={`${id}-state`} className="sr-only">Selecionar UF</label>
            <select
              id={`${id}-state`}
              value={selectedCode}
              onChange={(event) => {
                onStateSelect(event.target.value ? Number(event.target.value) : null)
                setHoveredCode(null)
                setFocusedCode(null)
              }}
              className="min-h-11 w-full rounded-md border border-surface/20 bg-navigation px-3 py-2 text-sm text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface"
            >
              <option value="">Nenhuma UF selecionada</option>
              {states.map((state) => (
                <option key={state.ibgeCode} value={state.ibgeCode}>
                  {state.name} ({state.abbreviation})
                </option>
              ))}
            </select>
          </div>
        </details>
      </div>
    </figure>
  )
}

export default BrazilStatesMap
