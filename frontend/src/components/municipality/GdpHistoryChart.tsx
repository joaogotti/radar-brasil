import { useEffect, useId, useRef, useState } from 'react'
import type { Indicator } from '../../types/indicator'
import { continuousGdpSegments } from '../../utils/economicIndicators'
import { formatThousandReais } from '../../utils/formatEconomicIndicator'

function GdpHistoryChart({ history }: { history: Indicator[] }) {
  const id = useId()
  const container = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(720)

  useEffect(() => {
    if (!container.current) return
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(260, entry.contentRect.width)))
    observer.observe(container.current)
    return () => observer.disconnect()
  }, [])

  const segments = continuousGdpSegments(history)
  const values = history.map((item) => Number(item.value))
  const minimum = Math.min(0, ...values)
  const maximum = Math.max(0, ...values)
  const range = maximum - minimum || 1
  const firstYear = history[0].reference_year
  const lastYear = history.at(-1)!.reference_year
  const left = 94
  const right = width - 18
  const top = 20
  const bottom = 208
  const x = (year: number) => firstYear === lastYear ? (left + right) / 2
    : left + (year - firstYear) / (lastYear - firstYear) * (right - left)
  const y = (value: number) => bottom - (value - minimum) / range * (bottom - top)
  const ticks = maximum === minimum ? [0] : [minimum, minimum + range / 2, maximum]
  const yearTicks = [...new Set([firstYear, ...(width > 500 ? [history[Math.floor(history.length / 2)].reference_year] : []), lastYear])]

  return (
    <div ref={container} className="min-w-0">
      <svg viewBox={`0 0 ${width} 250`} className="block w-full" role="img" aria-labelledby={`${id}-title ${id}-description`}>
        <title id={`${id}-title`}>Evolução do PIB de {firstYear} a {lastYear}</title>
        <desc id={`${id}-description`}>
          PIB municipal a preços correntes, em reais. {history.length} observações.
          {segments.length > 1 ? ' A linha é interrompida nos anos sem dados.' : ''}
          Os valores de cada ano estão na tabela disponível após o gráfico.
        </desc>
        {ticks.map((value) => (
          <g key={value}>
            <line x1={left} x2={right} y1={y(value)} y2={y(value)} className="stroke-border" strokeDasharray="3 5" />
            <text x={left - 12} y={y(value) + 4} textAnchor="end" className="fill-muted text-[11px]">
              {formatThousandReais(value.toFixed(6))}
            </text>
          </g>
        ))}
        {yearTicks.map((year) => (
          <text key={year} x={x(year)} y={235} textAnchor="middle" className="fill-muted text-xs">{year}</text>
        ))}
        {segments.map((segment) => (
          <polyline key={segment[0].reference_year} points={segment.map((item) => `${x(item.reference_year)},${y(Number(item.value))}`).join(' ')}
            fill="none" className="stroke-primary" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
        ))}
        {history.map((item) => (
          <circle key={item.reference_year} cx={x(item.reference_year)} cy={y(Number(item.value))} r={history.length === 1 ? 4 : 2.5} className="fill-primary" />
        ))}
      </svg>
      {history.length === 1 && <p className="text-xs text-muted">Há apenas uma observação disponível, de {firstYear}.</p>}
      {segments.length > 1 && <p className="text-xs text-muted">As interrupções na linha indicam anos sem dados.</p>}
      <details className="mt-4 border-t border-border/60 pt-4">
        <summary className="w-fit cursor-pointer rounded text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
          Ver valores por ano
        </summary>
        <table className="mt-4 w-full text-left text-sm tabular-nums">
          <caption className="sr-only">PIB municipal a preços correntes, em reais, por ano disponível</caption>
          <thead><tr className="border-b border-border"><th scope="col" className="py-2 font-medium">Ano</th><th scope="col" className="py-2 text-right font-medium">PIB (R$)</th></tr></thead>
          <tbody>{history.map((item) => (
            <tr key={item.reference_year} className="border-b border-border/50 last:border-0">
              <th scope="row" className="py-2 font-normal text-muted">{item.reference_year}</th>
              <td className="py-2 text-right text-navigation">{formatThousandReais(item.value, false)}</td>
            </tr>
          ))}</tbody>
        </table>
      </details>
    </div>
  )
}

export default GdpHistoryChart
