import { Link } from 'react-router'

function NotFoundPage() {
  return (
    <div className="mx-auto w-full max-w-7xl px-6 py-10 lg:px-8 lg:py-14">
      <p className="text-sm font-medium text-primary">404</p>
      <h1 className="mt-3 text-3xl tracking-tight text-navigation sm:text-4xl">Página não encontrada</h1>
      <p className="mt-4 max-w-prose text-base text-[#627289]">O endereço acessado não existe no Radar Brasil.</p>
      <Link to="/" className="mt-6 inline-flex min-h-11 items-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-surface hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
        Voltar ao início
      </Link>
    </div>
  )
}

export default NotFoundPage
