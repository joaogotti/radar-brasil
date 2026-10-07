import { Suspense, useEffect, useRef, type RefObject } from 'react'
import { Outlet, useLocation, useNavigationType } from 'react-router'
import Header from './Header'

function RouteContent({ main }: { main: RefObject<HTMLElement | null> }) {
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
  }, [pathname, hash, navigationType, main])

  return <Outlet />
}

function AppLayout() {
  const main = useRef<HTMLElement>(null)

  return (
    <div className="flex min-h-dvh flex-col">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-3 focus:text-primary focus:outline-2 focus:outline-offset-2 focus:outline-primary" onClick={(event) => {
        event.preventDefault()
        main.current?.focus()
      }}>Ir para o conteúdo</a>
      <Header />
      <main ref={main} id="main-content" tabIndex={-1} className="flex-1">
        <Suspense fallback={<p role="status" className="mx-auto max-w-7xl px-6 py-10 text-sm text-muted lg:px-8 lg:py-14">Carregando página…</p>}>
          <RouteContent main={main} />
        </Suspense>
      </main>
    </div>
  )
}

export default AppLayout
