import { useId } from 'react'
import type { State } from '../../types/state'
import type { Municipality } from '../../types/municipality'

interface ComparisonSelectorProps {
  label: string
  states: State[]
  stateCode: number | null
  municipalityCode: number | null
  municipalities: Municipality[]
  statesLoading: boolean
  municipalitiesLoading: boolean
  error: string | null
  indicatorStatus: string | null
  onStateChange: (code: number | null) => void
  onMunicipalityChange: (code: number | null) => void
}

const selectClass = 'mt-1.5 min-h-11 w-full min-w-0 rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-60'

function ComparisonSelector(props: ComparisonSelectorProps) {
  const id = useId()

  return (
    <fieldset className="min-w-0 rounded-panel border border-border bg-surface px-4 pt-3 pb-3 sm:px-5">
      <legend className="px-2 text-sm font-semibold text-navigation">{props.label}</legend>
      <label htmlFor={`${id}-state`} className="block text-xs text-muted">UF — {props.label}</label>
      <select id={`${id}-state`} className={selectClass} value={props.stateCode ?? ''}
        disabled={props.statesLoading || props.states.length === 0}
        onChange={(event) => props.onStateChange(event.target.value ? Number(event.target.value) : null)}>
        <option value="">{props.statesLoading ? 'Carregando UFs…' : 'Selecione uma UF'}</option>
        {props.states.map((state) => <option key={state.ibge_code} value={state.ibge_code}>{state.name} ({state.abbreviation})</option>)}
      </select>
      <label htmlFor={`${id}-municipality`} className="mt-3 block text-xs text-muted">Município — {props.label}</label>
      <select id={`${id}-municipality`} className={selectClass} value={props.municipalityCode ?? ''}
        disabled={props.stateCode === null || props.municipalitiesLoading || props.municipalities.length === 0}
        onChange={(event) => props.onMunicipalityChange(event.target.value ? Number(event.target.value) : null)}>
        <option value="">{props.municipalitiesLoading ? 'Carregando municípios…' : 'Selecione um município'}</option>
        {props.municipalities.map((municipality) => <option key={municipality.ibge_code} value={municipality.ibge_code}>{municipality.name}</option>)}
      </select>
      <div className="mt-2 min-h-5 text-xs text-muted" role={props.error ? 'alert' : 'status'} aria-atomic="true">
        {props.error ?? (props.municipalitiesLoading ? 'Carregando municípios…' : props.indicatorStatus)}
      </div>
    </fieldset>
  )
}

export default ComparisonSelector
