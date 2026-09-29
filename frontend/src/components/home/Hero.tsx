import BrazilStatesMap from '../maps/BrazilStatesMap'

function Hero() {
  return (
    <section
      aria-labelledby="hero-title"
      className="bg-navigation text-surface"
    >
      <div className="mx-auto grid max-w-7xl items-center gap-8 px-6 py-10 sm:py-12 lg:grid-cols-[1fr_1.15fr] lg:gap-10 lg:px-8 lg:py-8">
        <div>
          <p className="text-xs font-semibold tracking-widest text-surface/60">
            DADOS PÚBLICOS • BRASIL
          </p>
          <h1
            id="hero-title"
            className="mt-4 max-w-xl text-4xl leading-tight tracking-tight text-balance sm:text-5xl"
          >
            Dados do <span className="text-primary">Brasil</span>, de forma mais clara.
          </h1>
          <p className="mt-5 max-w-lg text-base leading-relaxed text-surface/70 sm:text-lg">
            Explore indicadores econômicos, sociais e demográficos de estados e
            municípios brasileiros em uma única plataforma.
          </p>
  
          <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <button
              type="button"
              className="min-h-11 rounded-md bg-primary px-5 py-3 text-sm font-semibold text-surface transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface motion-reduce:transition-none"
            >
              Explorar indicadores
            </button>
            <button
              type="button"
              className="min-h-11 rounded-md px-5 py-3 text-sm font-medium text-surface/80 transition-colors hover:bg-surface/5 hover:text-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface motion-reduce:transition-none"
            >
              Comparar municípios
            </button>
          </div>
        </div>
  
        <BrazilStatesMap />
      </div>
    </section>
  )
}

export default Hero
