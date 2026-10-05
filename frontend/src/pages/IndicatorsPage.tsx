import { useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router'
import IndicatorNavigation from '../components/indicators/IndicatorNavigation'
import IndicatorMunicipalityPicker from '../components/indicators/IndicatorMunicipalityPicker'
import IndicatorExplorer from '../components/indicators/IndicatorExplorer'
import { useMunicipalityIndicators } from '../hooks/useMunicipalityIndicators'
import { parseIndicatorSelection, serializeIndicatorSelection } from '../utils/indicatorExplorer'

function IndicatorsPage() {
  const [params, setParams] = useSearchParams()
  const selection = parseIndicatorSelection(params)
  const { indicator, municipality } = selection
  const result = useMunicipalityIndicators(municipality)
  const [pickerOpen, setPickerOpen] = useState(false)
  const pickerButton = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    const previous = document.title
    document.title = 'Indicadores | Radar Brasil'
    return () => { document.title = previous }
  }, [])

  function closePicker() {
    setPickerOpen(false)
    requestAnimationFrame(() => pickerButton.current?.focus())
  }

  return <div className="mx-auto max-w-7xl px-6 py-10 lg:px-8 lg:py-14">
    <h1 className="text-3xl tracking-tight text-navigation sm:text-4xl">Explore os indicadores do Brasil</h1>
    <p className="mt-3 max-w-2xl text-base text-muted">Navegue pelos temas, conheça os indicadores e consulte os dados de um município.</p>
    {selection.invalidIndicator && <p role="status" className="mt-4 text-sm text-muted">Indicador inválido na URL. Exibindo PIB; escolha outro indicador abaixo.</p>}
    {selection.invalidMunicipality && <p role="status" className="mt-4 text-sm text-muted">Código municipal inválido na URL. Selecione um município para consultar.</p>}
    <IndicatorNavigation selected={indicator} onSelect={code => setParams(serializeIndicatorSelection(code, municipality))} />
    <div className="mt-8 grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
      <section aria-labelledby="indicator-description-title" className="min-w-0 rounded-panel border border-border bg-surface p-5 shadow-surface sm:p-6">
        <h2 id="indicator-description-title" className="text-xl break-words text-navigation">{indicator.name}</h2>
        <p className="mt-3 max-w-prose text-sm leading-relaxed text-muted">{indicator.description}</p>
        <dl className="mt-5 space-y-4 border-t border-border/60 pt-5 text-sm">
          <div><dt className="font-medium">Fonte</dt><dd className="mt-1 text-muted">{indicator.source}</dd></div>
          <div><dt className="font-medium">Unidade</dt><dd className="mt-1 text-muted">{indicator.unit}</dd></div>
          <div><dt className="font-medium">Referências existentes no projeto</dt><dd className="mt-1 text-muted">{indicator.references}</dd></div>
        </dl>
        <p className="mt-3 text-xs leading-relaxed text-muted">A disponibilidade pode variar por município.</p>
        {indicator.note && <p className="mt-5 max-w-prose text-xs leading-relaxed text-muted">{indicator.note}</p>}
      </section>
      <section aria-labelledby="indicator-result-title" className="min-w-0 rounded-panel border border-border bg-surface p-5 shadow-surface sm:p-6">
        <h2 id="indicator-result-title" className="text-lg text-navigation">Consultar no município</h2>
        <div hidden={pickerOpen}>
          <IndicatorExplorer indicator={indicator} municipalityCode={municipality} {...result} />
          <button ref={pickerButton} type="button" aria-expanded={pickerOpen} aria-controls="indicator-municipality-picker" onClick={() => setPickerOpen(true)} className={municipality === null
            ? 'mt-4 min-h-11 rounded-md border border-border px-4 py-2 text-sm font-medium text-primary hover:bg-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary'
            : 'mt-4 min-h-11 text-sm text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary'}>{municipality === null ? 'Escolher município' : 'Trocar município'}</button>
        </div>
        <div id="indicator-municipality-picker">{pickerOpen && <IndicatorMunicipalityPicker onClose={closePicker} onSelect={code => { setParams(serializeIndicatorSelection(indicator.code, code)); closePicker() }} />}</div>
      </section>
    </div>
  </div>
}

export default IndicatorsPage
