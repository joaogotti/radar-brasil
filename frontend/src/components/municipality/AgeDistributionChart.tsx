import { useEffect, useId, useRef, useState } from 'react'
import type { AgeDistribution } from '../../types/ageDistribution'
import { ageDistributionRows, percentageGeometry } from '../../utils/ageDistribution'
import { formatPercentage, formatPopulation } from '../../utils/formatDemographicIndicator'

function AgeDistributionChart({ distribution }: { distribution: AgeDistribution }) {
  const id = useId()
  const container = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(0)
  const rows = ageDistributionRows(distribution.data)
  const [selectedAge, setSelectedAge] = useState<number | null>(null)
  const selected = rows.find((row) => row.age.ibge_code === selectedAge) ?? rows.at(-1)
  const geometry = percentageGeometry(distribution.data)
  const center = width / 2
  const gap = 76
  const side = Math.max(0, (width - gap) / 2 - 12)
  const left = center - gap / 2
  const right = center + gap / 2
  const rowHeight = 26
  const top = 38
  const bottom = top + rows.length * rowHeight
  const ticks = width >= 500 ? [0, geometry.domain / 2, geometry.domain] : [0, geometry.domain]

  useEffect(() => {
    if (!container.current) return
    const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width))
    observer.observe(container.current)
    return () => observer.disconnect()
  }, [])

  return (
    <div ref={container} className="min-w-0">
      {geometry.hasPercentages && width > 0 ? <svg viewBox={`0 0 ${width} ${bottom + 34}`} className="block w-full" role="img" aria-labelledby={`${id}-title ${id}-description`}>
        <title id={`${id}-title`}>Pirâmide etária de {distribution.municipality.name}, {distribution.reference_year}</title>
        <desc id={`${id}-description`}>Homens à esquerda e mulheres à direita, na mesma escala de percentual da população municipal total. Idades mais altas no topo. Traços indicam valores não disponíveis. Consulte os valores no seletor e na tabela após o gráfico.</desc>
        <text x={left - side / 2} y={16} textAnchor="middle" className="fill-navigation text-xs font-medium">Homens</text>
        <text x={center} y={16} textAnchor="middle" className="fill-muted text-xs">Idade</text>
        <text x={right + side / 2} y={16} textAnchor="middle" className="fill-navigation text-xs font-medium">Mulheres</text>
        {ticks.map((tick) => <g key={tick}>
          {[left - tick / geometry.domain * side, right + tick / geometry.domain * side].map((x, index) => <g key={index}>
            <line x1={x} x2={x} y1={top - 6} y2={bottom} className="stroke-border" strokeDasharray="3 5" />
            <text x={x} y={bottom + 22} textAnchor="middle" className="fill-muted text-[11px]">{`${tick.toLocaleString('pt-BR')}%`}</text>
          </g>)}
        </g>)}
        {rows.map((row, index) => {
          const y = top + index * rowHeight
          const men = geometry.length(row.men, side)
          const women = geometry.length(row.women, side)
          return <g key={row.age.ibge_code} onPointerEnter={(event) => { if (event.pointerType === 'mouse') setSelectedAge(row.age.ibge_code) }} onClick={() => setSelectedAge(row.age.ibge_code)} className="cursor-pointer">
            <rect x={0} y={y - 5} width={width} height={rowHeight} rx={4} className={selected?.age.ibge_code === row.age.ibge_code ? 'fill-background/40' : 'fill-transparent'} />
            {men !== null ? <rect x={left - men} y={y} width={men} height={19} rx={2} className="fill-navigation" /> : <text x={left - 12} y={y + 14} textAnchor="end" className="fill-muted text-xs">—</text>}
            {women !== null ? <rect x={right} y={y} width={women} height={19} rx={2} className="fill-primary" /> : <text x={right + 12} y={y + 14} className="fill-muted text-xs">—</text>}
            <text x={center} y={y + 14} textAnchor="middle" className="fill-navigation text-xs">{row.age.max_age === null ? `${row.age.min_age}+` : `${row.age.min_age}–${row.age.max_age}`}</text>
          </g>
        })}
      </svg> : !geometry.hasPercentages && <p className="text-sm text-muted">Os percentuais não estão disponíveis. Consulte os dados de população abaixo.</p>}
      <p className="mt-3 text-xs leading-relaxed text-muted">Percentuais publicados pelo IBGE, sem normalização. A soma pode diferir de 100%. “—” indica dado não disponível; zero permanece zero.</p>
      {selected && <div className="mt-5 border-t border-border/60 pt-4">
        <label htmlFor={`${id}-age`} className="block text-sm font-medium text-navigation">Faixa selecionada</label>
        <select id={`${id}-age`} value={selected.age.ibge_code} onChange={(event) => setSelectedAge(Number(event.target.value))} className="mt-2 min-h-11 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-navigation focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary sm:w-56">
          {rows.map((row) => <option key={row.age.ibge_code} value={row.age.ibge_code}>{row.age.label}</option>)}
        </select>
        <dl className="mt-4 grid grid-cols-2 gap-4 text-sm">
          {[{ label: 'Homens', cell: selected.men }, { label: 'Mulheres', cell: selected.women }].map(({ label, cell }) => (
            <div key={label} className="min-w-0"><dt className="font-medium text-navigation">{label}</dt><dd className="mt-2 break-words text-muted tabular-nums"><span className="block">{formatPopulation(cell?.population ?? null)}</span><span className="mt-1 block">{formatPercentage(cell?.percentage ?? null)}</span></dd></div>
          ))}
        </dl>
      </div>}
      <details className="mt-5 border-t border-border/60 pt-4">
        <summary className="w-fit cursor-pointer rounded py-2 text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">Ver valores por faixa etária</summary>
        <table className="mt-4 w-full table-fixed text-left text-xs tabular-nums sm:text-sm">
          <caption className="sr-only">População e percentual da população municipal total por faixa etária e sexo, Censo {distribution.reference_year}</caption>
          <thead><tr className="border-b border-border"><th scope="col" className="w-1/4 py-2 font-medium">Faixa</th><th scope="col" className="px-1 py-2 font-medium">Homens</th><th scope="col" className="px-1 py-2 font-medium">Mulheres</th></tr></thead>
          <tbody>{rows.map((row) => <tr key={row.age.ibge_code} className="border-b border-border/50 last:border-0">
            <th scope="row" className="py-3 pr-1 font-normal text-muted">{row.age.label}</th>
            {[row.men, row.women].map((cell, index) => <td key={index} className="px-1 py-3 align-top break-words text-navigation"><span className="block">{formatPopulation(cell?.population ?? null)}</span><span className="mt-1 block text-muted">{formatPercentage(cell?.percentage ?? null)}</span></td>)}
          </tr>)}</tbody>
        </table>
      </details>
    </div>
  )
}

export default AgeDistributionChart
