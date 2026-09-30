import type { Indicator } from '../../types/indicator'
import { economicComposition, gdpHistory, latestEconomicIndicator } from '../../utils/economicIndicators'
import { formatReais, formatThousandReais } from '../../utils/formatEconomicIndicator'
import GdpHistoryChart from './GdpHistoryChart'

interface MunicipalityEconomyProps {
  indicators: Indicator[]
  loading: boolean
}

function MunicipalityEconomy({ indicators, loading }: MunicipalityEconomyProps) {
  const gdp = latestEconomicIndicator(indicators, 'municipal_gdp')
  const perCapita = latestEconomicIndicator(indicators, 'municipal_gdp_per_capita')
  const composition = economicComposition(indicators)
  const history = gdpHistory(indicators)
  const highlights = [
    { label: 'PIB', indicator: gdp, format: formatThousandReais },
    { label: 'PIB per capita', indicator: perCapita, format: formatReais },
  ]

  return (
    <section aria-labelledby="municipality-economy-title" aria-busy={loading} className="mt-12 border-t border-border/60 pt-10">
      <h2 id="municipality-economy-title" className="text-lg text-navigation">Economia</h2>
      <p className="mt-2 text-sm text-muted">Produção e atividades econômicas do município.</p>
      {loading && <p role="status" className="mt-3 text-sm text-muted">Carregando indicadores econômicos…</p>}

      <h3 className="sr-only">Destaques econômicos</h3>
      <dl className="mt-5 grid gap-4 sm:grid-cols-2">
        {highlights.map(({ label, indicator, format }) => (
          <div key={label} className="min-w-0 rounded-panel border border-border bg-surface px-5 py-5 shadow-surface sm:px-6">
            <dt className="text-xs font-medium tracking-wide text-muted uppercase">{label}</dt>
            <dd className="mt-3">
              {loading ? <div aria-hidden="true" className="h-9 w-3/4 rounded bg-border/60" />
                : indicator ? <>
                  <span className="block text-3xl font-semibold tracking-tight break-words text-navigation tabular-nums">{format(indicator.value)}</span>
                  <span className="mt-2 block text-xs text-muted">{indicator.reference_year} • A preços correntes</span>
                </> : <span className="text-sm text-muted">Dado não disponível.</span>}
            </dd>
          </div>
        ))}
      </dl>

      {!loading && <>
        <div className="mt-8 rounded-panel border border-border bg-surface p-5 shadow-surface sm:p-6">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h3 className="text-base font-medium text-navigation">Composição econômica</h3>
            {composition && <p className="text-xs text-muted">Ano {composition.year}</p>}
          </div>
          <p className="mt-2 text-sm text-muted">Valores adicionados brutos por setor, a preços correntes.</p>
          {composition ? <>
            <dl className="mt-5 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
              {composition.sectors.map(({ code, label, indicator }) => (
                <div key={code} className="min-w-0 border-t border-border/60 pt-4">
                  <dt className="text-sm text-muted">{label}</dt>
                  <dd className="mt-2 text-xl font-semibold break-words text-navigation tabular-nums">{formatThousandReais(indicator.value)}</dd>
                </div>
              ))}
            </dl>
            <p className="mt-5 text-xs leading-relaxed text-muted">Serviços exclui administração, defesa, educação e saúde públicas e seguridade social, apresentados em Administração pública.</p>
          </> : <p className="mt-5 text-sm text-muted">Não há dados dos quatro setores para um mesmo ano.</p>}
        </div>

        <div className="mt-8 rounded-panel border border-border bg-surface p-5 shadow-surface sm:p-6">
          <h3 className="text-base font-medium text-navigation">Evolução do PIB</h3>
          <p className="mt-2 text-sm text-muted">PIB municipal a preços correntes, sem ajuste pela inflação.</p>
          <div className="mt-5">
            {history.length > 0 ? <GdpHistoryChart history={history} /> : <p className="text-sm text-muted">Série histórica não disponível.</p>}
          </div>
        </div>
        <p className="mt-5 text-xs text-muted">Fonte: IBGE • Produto Interno Bruto dos Municípios. Os períodos podem variar entre os indicadores.</p>
      </>}
    </section>
  )
}

export default MunicipalityEconomy
