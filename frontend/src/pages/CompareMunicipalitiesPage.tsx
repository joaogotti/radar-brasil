import { useRef, useState } from 'react'
import { useSearchParams } from 'react-router'
import ComparisonMunicipalityPicker from '../components/comparison/ComparisonMunicipalityPicker'
import ComparisonIndicatorGroup, { type ComparisonResultSide } from '../components/comparison/ComparisonIndicatorGroup'
import { useMunicipalityIndicators } from '../hooks/useMunicipalityIndicators'
import { comparisonGroups, comparisonValues } from '../utils/comparisonIndicators'
import { parseComparisonSelection, removeComparisonMunicipality, selectComparisonMunicipality, serializeComparisonSelection } from '../utils/comparisonSelection'

const buttonClass = 'min-h-11 rounded-md border border-border px-4 py-2 text-sm font-medium text-primary hover:bg-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary'

function CompareMunicipalitiesPage() {
  const [parameters, setParameters] = useSearchParams()
  const selection = parseComparisonSelection(parameters.getAll('municipios').join(',') || null)
  const key = parameters.toString()
  const [editor, setEditor] = useState<{ index: 0 | 1, key: string } | null>(null)
  const active = editor?.key === key ? editor.index : null
  const buttons = useRef<(HTMLButtonElement | null)[]>([])
  const a = useMunicipalityIndicators(selection.codes[0] ?? null)
  const b = useMunicipalityIndicators(selection.codes[1] ?? null)
  const results = [a, b] as const
  const sides = results.map((result, index): ComparisonResultSide => ({
    name: result.data ? `${result.data.municipality.name} / ${result.data.municipality.state.abbreviation}` : selection.codes[index] ? `Município ${selection.codes[index]}` : `Município ${index === 0 ? 'A' : 'B'}`,
    selected: selection.codes[index] !== undefined,
    loading: result.loading,
    error: result.error,
    values: comparisonValues(result.data?.indicators ?? []),
  })) as [ComparisonResultSide, ComparisonResultSide]

  function closeEditor(index: 0 | 1) {
    setEditor(null)
    requestAnimationFrame(() => buttons.current[index]?.focus())
  }

  function updateSelection(codes: number[], focusIndex: 0 | 1) {
    const next = new URLSearchParams(parameters)
    const value = serializeComparisonSelection(codes)
    if (value) next.set('municipios', value)
    else next.delete('municipios')
    setParameters(next)
    closeEditor(focusIndex)
  }

  return (
    <div className="mx-auto max-w-7xl px-6 py-10 lg:px-8 lg:py-14">
      <h1 className="text-3xl tracking-tight text-navigation sm:text-4xl">Comparar municípios</h1>
      <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted">Analise indicadores de dois municípios lado a lado, com suas referências e períodos disponíveis.</p>
      {selection.invalid && <p role="status" className="mt-4 text-sm text-muted">Alguns códigos da URL são inválidos, repetidos ou excedem o limite de dois municípios. Apenas os códigos válidos foram considerados.</p>}
      <div className="mt-8 grid gap-4 md:grid-cols-2">
        {([0, 1] as const).map((index) => {
          const side = sides[index]
          const result = results[index]
          return <section key={index} aria-labelledby={`comparison-slot-${index}`} aria-busy={side.loading} className="min-w-0 rounded-panel border border-border bg-surface p-5 shadow-surface sm:p-6">
            <h2 id={`comparison-slot-${index}`} className="text-sm font-medium text-muted">Município {index === 0 ? 'A' : 'B'}</h2>
            <p className="mt-3 text-xl font-semibold break-words text-navigation">{side.selected ? side.name : 'Selecione um município'}</p>
            {side.selected && <p className="mt-2 text-xs text-muted">Código IBGE {selection.codes[index]}</p>}
            {side.loading && <p role="status" className="mt-3 text-sm text-muted">Carregando indicadores…</p>}
            {side.error && <div className="mt-3"><p role="alert" className="text-sm text-muted">{result.notFound ? 'Município não encontrado. Altere ou remova esta seleção.' : side.error}</p><button type="button" className={`${buttonClass} mt-3`} onClick={result.retry} aria-label={`Tentar novamente município ${index === 0 ? 'A' : 'B'}`}>Tentar novamente</button></div>}
            <div className="mt-4 flex flex-wrap gap-3">
              <button ref={(element) => { buttons.current[index] = element }} type="button" className={buttonClass} aria-expanded={active === index} aria-label={`${side.selected ? 'Alterar' : 'Selecionar'} município ${index === 0 ? 'A' : 'B'}`} onClick={() => active === index ? closeEditor(index) : setEditor({ index, key })}>{side.selected ? 'Alterar' : 'Selecionar'}</button>
              {side.selected && <button type="button" className={buttonClass} aria-label={`Remover ${side.name} da comparação`} onClick={() => updateSelection(removeComparisonMunicipality(selection.codes, index), index === 0 ? 0 : 1)}>Remover</button>}
            </div>
          </section>
        })}
      </div>
      {active !== null && <ComparisonMunicipalityPicker key={`${key}-${active}`} side={active === 0 ? 'A' : 'B'} excludedCode={selection.codes[active === 0 ? 1 : 0] ?? null} onClose={() => closeEditor(active)} onSelect={(code) => updateSelection(selectComparisonMunicipality(selection.codes, active, code), Math.min(active, selection.codes.length) as 0 | 1)} />}
      {selection.codes.length < 2 && <p role="status" className="mt-6 text-sm text-muted">{selection.codes.length === 0 ? 'Selecione dois municípios para começar.' : 'Selecione o segundo município para completar a comparação.'}</p>}
      {results.some((result) => result.data) && <>
        {comparisonGroups.map((group) => <ComparisonIndicatorGroup key={group.id} group={group} sides={sides} />)}
        <p className="mt-6 text-xs leading-relaxed text-muted">Fontes: IBGE e INEP. Cada valor apresenta sua referência; os períodos disponíveis podem variar entre municípios e indicadores.</p>
      </>}
    </div>
  )
}

export default CompareMunicipalitiesPage
