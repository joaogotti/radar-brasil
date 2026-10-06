import { useEffect, useRef } from 'react'
import { Outlet, useLocation, useNavigationType } from 'react-router'
import Header from './Header'

function AppLayout() {
  const main = useRef<HTMLElement>(null)
  const previousPath = useRef<string | null>(null)
  const { pathname, hash } = useLocation()
  const navigationType = useNavigationType()

  useEffect(() => {
    const previous = previousPath.current
    previousPath.current = pathname
    if (previous === pathname || hash || (previous !== null && navigationType === 'POP')) return

    const heading = main.current?.querySelector('h1')
    if (heading) {
      heading.tabIndex = -1
      heading.focus({ preventScroll: true })
    }
  }, [pathname, hash, navigationType])

  return (
    <div className="flex min-h-dvh flex-col">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-3 focus:text-primary focus:outline-2 focus:outline-offset-2 focus:outline-primary" onClick={(event) => {
        event.preventDefault()
        main.current?.focus()
      }}>Ir para o conteúdo</a>
      <Header />
      <main ref={main} id="main-content" tabIndex={-1} className="flex-1">
        <Outlet />
      </main>
    </div>
  )
}

export default AppLayout
