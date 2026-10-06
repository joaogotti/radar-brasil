import { useEffect, useId, useRef, useState } from 'react'
import { Link } from 'react-router'
import { useMunicipalitySearch } from '../../hooks/useMunicipalitySearch'
import { headerMunicipalityResults } from '../../utils/headerMunicipalitySearch'

function SearchResults({ query, id, onSelect }: { query: string, id: string, onSelect: () => void }) {
  const { data, loading, error } = useMunicipalitySearch({ search: query, state: null, page: 1, perPage: 100 })
  const municipalities = !loading && !error ? headerMunicipalityResults(data?.data ?? [], query) : []

  return (
    <div id={id} className="absolute inset-x-0 top-full z-20 mt-2 max-h-72 overflow-y-auto rounded-md border border-border bg-surface text-foreground shadow-surface">
      <p role="status" className={municipalities.length > 0 ? 'sr-only' : 'px-4 py-3 text-sm text-muted'}>
        {loading ? 'Buscando municípios…' : error ? 'Não foi possível buscar. Feche e tente novamente.'
          : municipalities.length === 0 ? 'Nenhum município encontrado.' : `${municipalities.length} resultados apresentados.`}
      </p>
      {municipalities.length > 0 && <ul aria-label="Municípios encontrados" className="divide-y divide-border">
        {municipalities.map((municipality) => <li key={municipality.ibge_code}>
          <Link to={`/municipios/${municipality.ibge_code}`} onClick={onSelect}
            className="flex min-h-11 items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-background focus:bg-background focus:underline focus:outline-2 focus:-outline-offset-2 focus:outline-primary">
            <span className="min-w-0 break-words font-medium">{municipality.name}</span>
            <span className="shrink-0 text-muted">{municipality.state.abbreviation}</span>
          </Link>
        </li>)}
      </ul>}
    </div>
  )
}

function HeaderMunicipalitySearch({ onSelect }: { onSelect: () => void }) {
  const id = useId()
  const container = useRef<HTMLDivElement>(null)
  const input = useRef<HTMLInputElement>(null)
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const search = query.trim()
  const expanded = open && search.length > 0

  useEffect(() => {
    if (!expanded) return
    function dismiss(event: PointerEvent) {
      if (event.target instanceof Node && !container.current?.contains(event.target)) setOpen(false)
    }
    document.addEventListener('pointerdown', dismiss)
    return () => document.removeEventListener('pointerdown', dismiss)
  }, [expanded])

  return (
    <div ref={container} className="relative" onBlur={(event) => {
      if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false)
    }} onKeyDown={(event) => {
      if (event.key === 'Escape' && expanded) {
        event.preventDefault()
        event.stopPropagation()
        input.current?.focus()
        setOpen(false)
      }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()
        setOpen(true)
        const links = [...event.currentTarget.querySelectorAll<HTMLAnchorElement>('ul a')]
        const current = links.indexOf(document.activeElement as HTMLAnchorElement)
        const next = current === -1 ? (event.key === 'ArrowDown' ? 0 : links.length - 1)
          : current + (event.key === 'ArrowDown' ? 1 : -1)
        if (links.length > 0) (links[next] ?? input.current)?.focus()
      }
    }}>
      <label htmlFor={id} className="sr-only">Buscar município</label>
      <svg className="pointer-events-none absolute top-3 left-3 size-4 text-surface/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" aria-hidden="true">
        <circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4 4" />
      </svg>
      <input ref={input} id={id} type="search" autoComplete="off" maxLength={100} placeholder="Buscar município"
        aria-controls={expanded ? `${id}-results` : undefined}
        value={query} onChange={(event) => { setQuery(event.target.value); setOpen(true) }} onFocus={() => setOpen(true)}
        onClick={() => setOpen(true)}
        className="h-10 w-full rounded-md border border-surface/15 bg-surface/5 pr-3 pl-9 text-sm placeholder:text-surface/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface" />
      {expanded && <SearchResults query={search} id={`${id}-results`} onSelect={() => {
        setOpen(false)
        setQuery('')
        onSelect()
      }} />}
    </div>
  )
}

export default HeaderMunicipalitySearch
