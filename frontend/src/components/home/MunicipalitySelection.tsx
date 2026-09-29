import { useId } from 'react'
import type { Municipality } from '../../types/municipality'

interface MunicipalitySelectionProps {
  municipalities: Municipality[]
  selectedIbgeCode: number | null
  onSelect: (ibgeCode: number | null) => void
}

function MunicipalitySelection({ municipalities, selectedIbgeCode, onSelect }: MunicipalitySelectionProps) {
  const id = useId()

  return (
    <div className="mt-4 max-w-sm">
      <label htmlFor={id} className="block text-xs text-surface/70">Município</label>
      <select
        id={id}
        value={selectedIbgeCode ?? ''}
        onChange={(event) => onSelect(event.target.value ? Number(event.target.value) : null)}
        className="mt-2 min-h-11 w-full rounded-md border border-surface/20 bg-navigation px-3 py-2 text-sm text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface"
      >
        <option value="">Selecione um município</option>
        {municipalities.map((municipality) => (
          <option key={municipality.ibge_code} value={municipality.ibge_code}>{municipality.name}</option>
        ))}
      </select>
    </div>
  )
}

export default MunicipalitySelection
