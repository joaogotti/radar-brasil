import { useEffect, useId, useRef, useState } from 'react'
import type { Indicator } from '../../types/indicator'
import { continuousIdebSegments, idebEditions, idebHistory, idebSeries } from '../../utils/educationIndicators'
import { formatIdeb } from '../../utils/formatEducationIndicator'

function IdebHistoryChart({ indicators }: { indicators: Indicator[] }) {
  const id = useId()
  const container = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(0)
  const series = idebSeries.map((definition) => ({ ...definition, history: idebHistory(indicators, definition.code) }))
  const years = idebEditions.filter((year) => series.some(({ history }) => history.some((item) => item.reference_year === year)))
  const first = years[0]
  const last = years.at(-1)
  const editions = idebEditions.filter((year) => first !== undefined && last !== undefined && year >= first && year <= last)
  const left = 28
  const right = width - 20
  const x = (year: number) => editions.length <= 1 ? (left + right) / 2 : left + editions.indexOf(year as typeof editions[number]) / (editions.length - 1) * (right - left)
  const y = (value: string) => 176 - Number(value) / 10 * 156
  const ticks = width >= 600 ? editions.filter((_, index) => index % 2 === 0 || index === editions.length - 1)
    : [...new Set([first, ...(width >= 360 ? [editions[Math.floor(editions.length / 2)]] : []), last])].filter((year) => year !== undefined)

  useEffect(() => {
    if (!container.current) return
    const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width))
    observer.observe(container.current)
    return () => observer.disconnect()
  }, [])

  return (
    <div ref={container} className="min-w-0">
      {years.length === 0 ? <p className="text-sm text-muted">Resultado não disponível.</p> : <>
        <ul aria-label="Séries do IDEB, rede pública" className="mb-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted">
          {series.map(({ code, label, history }, index) => <li key={code} className="flex items-center gap-2">
            <svg aria-hidden="true" width="26" height="12" className="shrink-0"><line x1="0" x2="26" y1="6" y2="6" strokeWidth="2.5" strokeDasharray={index === 1 ? '5 4' : undefined} className={index === 0 ? 'stroke-primary' : 'stroke-navigation'} /></svg>
            <span>{label}{history.length === 0 ? ' · Resultado não disponível' : ''}</span>
          </li>)}
        </ul>
        {width > 0 && <svg viewBox={`0 0 ${width} 216`} className="block w-full" role="img" aria-labelledby={`${id}-title ${id}-description`}>
          <title id={`${id}-title`}>Evolução do IDEB · Rede pública · {first === last ? first : `${first} a ${last}`}</title>
          <desc id={`${id}-description`}>Anos iniciais em linha contínua e anos finais em linha tracejada, na mesma escala de zero a dez. As linhas são interrompidas nas edições sem resultado. Os valores estão na tabela após o gráfico.</desc>
          {[0, 2, 4, 6, 8, 10].map((value) => <g key={value}>
            <line x1={left} x2={right} y1={y(String(value))} y2={y(String(value))} className="stroke-border" strokeDasharray="3 5" />
            <text x={left - 9} y={y(String(value)) + 4} textAnchor="end" className="fill-muted text-[11px]">{value}</text>
          </g>)}
          {ticks.map((year) => <text key={year} x={x(year)} y={205} textAnchor="middle" className="fill-muted text-xs">{year}</text>)}
          {series.map(({ code, history }, index) => <g key={code} aria-label={idebSeries[index].label}>
            {continuousIdebSegments(history).filter((segment) => segment.length > 1).map((segment) => <polyline key={segment[0].reference_year}
              points={segment.map((item) => `${x(item.reference_year)},${y(item.value)}`).join(' ')} fill="none" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round"
              strokeDasharray={index === 1 ? '5 4' : undefined} className={index === 0 ? 'stroke-primary' : 'stroke-navigation'} />)}
            {history.map((item) => index === 0
              ? <circle key={item.reference_year} cx={x(item.reference_year)} cy={y(item.value)} r={3.5} className="fill-primary" />
              : <rect key={item.reference_year} x={x(item.reference_year) - 3} y={y(item.value) - 3} width={6} height={6} className="fill-navigation" />)}
          </g>)}
        </svg>}
        <p className="mt-3 text-xs leading-relaxed text-muted">{years.length === 1 ? `Há resultados disponíveis apenas para ${first}.` : 'As linhas são interrompidas nas edições sem resultado. As edições apresentadas possuem periodicidade bienal.'}</p>
        <details className="mt-4 border-t border-border/60 pt-4">
          <summary className="w-fit cursor-pointer rounded py-2 text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">Ver valores por ano</summary>
          <table className="mt-4 w-full table-fixed text-left text-xs tabular-nums sm:text-sm">
            <caption className="sr-only">IDEB da rede pública por edição, anos iniciais e anos finais</caption>
            <thead><tr className="border-b border-border"><th scope="col" className="w-1/5 py-2 font-medium">Ano</th>{idebSeries.map(({ code, label }) => <th key={code} scope="col" className="px-1 py-2 font-medium">{label}</th>)}</tr></thead>
            <tbody>{idebEditions.map((year) => <tr key={year} className="border-b border-border/50 last:border-0">
              <th scope="row" className="py-3 font-normal text-muted">{year}</th>
              {series.map(({ code, history }) => {
                const item = history.find((item) => item.reference_year === year)
                return <td key={code} className="px-1 py-3 align-top break-words text-navigation">{item ? formatIdeb(item.value) : 'Resultado não disponível'}</td>
              })}
            </tr>)}</tbody>
          </table>
        </details>
      </>}
    </div>
  )
}

export default IdebHistoryChart
