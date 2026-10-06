import { useId, useRef, useState } from 'react'
import { NavLink, useLocation } from 'react-router'
import HeaderMunicipalitySearch from './HeaderMunicipalitySearch'

const navigationItems: { label: string, to?: string }[] = [
  { label: 'Início', to: '/' },
  { label: 'Indicadores', to: '/indicadores' },
  { label: 'Municípios', to: '/municipios' },
  { label: 'Comparar', to: '/comparar' },
]

const navigationClass = 'block border-b-2 border-transparent py-2 text-sm font-medium whitespace-nowrap text-surface/70 transition-colors hover:border-surface/30 hover:text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface aria-[current=page]:border-primary aria-[current=page]:text-surface'

function Header() {
  const [menuOpen, setMenuOpen] = useState(false)
  const menuId = useId()
  const location = useLocation()
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
                  <NavLink to={item.to} end={item.to === '/'} className={navigationClass} onClick={() => setMenuOpen(false)}>
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
          {menuOpen && <div className="mt-3 sm:hidden"><HeaderMunicipalitySearch key={location.key} onSelect={() => setMenuOpen(false)} /></div>}
        </nav>

        <div className="ml-auto flex items-center gap-2 sm:gap-3">
          <div className="hidden w-72 sm:block lg:w-80">
            <HeaderMunicipalitySearch key={location.key} onSelect={() => setMenuOpen(false)} />
          </div>

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
