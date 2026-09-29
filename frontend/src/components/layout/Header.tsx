import { useId, useRef, useState } from 'react'
import { NavLink } from 'react-router'

const navigationItems: { label: string, to?: string }[] = [
  { label: 'Início', to: '/' },
  { label: 'Indicadores' },
  { label: 'Municípios', to: '/municipios' },
  { label: 'Comparar' },
]

const navigationClass = 'block border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-surface/70 transition-colors hover:border-surface/30 hover:text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface aria-[current=page]:border-primary aria-[current=page]:text-surface'

function Header() {
  const [menuOpen, setMenuOpen] = useState(false)
  const menuId = useId()
  const menuButton = useRef<HTMLButtonElement>(null)

  return (
    <header className="relative z-10 bg-navigation text-surface" onKeyDown={(event) => {
      if (event.key === 'Escape' && menuOpen) {
        setMenuOpen(false)
        menuButton.current?.focus()
      }
    }}>
      <div className="mx-auto flex h-18 max-w-7xl items-center gap-6 px-6 lg:px-8 xl:gap-10">
        <div className="shrink-0 text-xl leading-none font-semibold tracking-tight whitespace-nowrap sm:text-2xl" aria-label="Radar Brasil">
          <span>Radar</span>{' '}
          <span className="text-primary">Brasil</span>
        </div>

        <nav id={menuId} className={`${menuOpen ? 'block' : 'hidden'} absolute inset-x-0 top-full border-t border-surface/15 bg-navigation px-6 py-3 lg:static lg:block lg:border-0 lg:p-0`} aria-label="Navegação principal">
          <ul className="flex flex-col gap-2 lg:flex-row lg:items-center lg:gap-5">
            {navigationItems.map((item) => (
              <li key={item.label}>
                {item.to ? (
                  <NavLink to={item.to} end className={navigationClass} onClick={() => setMenuOpen(false)}>
                    {item.label}
                  </NavLink>
                ) : (
                  <button type="button" disabled title="Em breve" className={navigationClass}>
                    {item.label}
                  </button>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <div className="ml-auto flex items-center gap-2 sm:gap-3">
          <div className="relative hidden sm:block">
            <label htmlFor="header-search" className="sr-only">
              Buscar indicador ou município
            </label>
            <svg
              className="pointer-events-none absolute top-3 left-3 size-4 text-surface/60"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              aria-hidden="true"
            >
              <circle cx="10.5" cy="10.5" r="6.5" />
              <path d="m16 16 4 4" />
            </svg>
            <input
              id="header-search"
              type="search"
              placeholder="Buscar indicador ou município"
              className="h-10 w-72 rounded-md border border-surface/15 bg-surface/5 pr-3 pl-9 text-sm placeholder:text-surface/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface lg:w-80"
            />
          </div>

          <button
            type="button"
            aria-label="Favoritos"
            className="flex size-11 shrink-0 items-center justify-center rounded-md text-surface/70 hover:bg-surface/10 hover:text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface"
          >
            <svg
              className="size-5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="M6 4h12v17l-6-4-6 4V4Z" />
            </svg>
          </button>

          <button
            type="button"
            aria-label="Menu principal"
            ref={menuButton}
            aria-expanded={menuOpen}
            aria-controls={menuId}
            onClick={() => setMenuOpen((open) => !open)}
            className="flex size-11 shrink-0 items-center justify-center rounded-md text-surface/70 hover:bg-surface/10 hover:text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface lg:hidden"
          >
            <svg
              className="size-5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              aria-hidden="true"
            >
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>
    </header>
  )
}

export default Header
