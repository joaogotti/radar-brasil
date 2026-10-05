import { formatIndicator } from './formatIndicator.ts'
import { formatReais, formatThousandReais } from './formatEconomicIndicator.ts'
import { formatAge, formatPercentage, formatRatio } from './formatDemographicIndicator.ts'
import { formatEducationCount, formatIdeb } from './formatEducationIndicator.ts'
import { demographicIndicators, livingConditionIndicators } from './demographicIndicators.ts'
import { schoolCensusIndicators } from './educationIndicators.ts'

export const indicatorThemes = [
  { id: 'economy', name: 'Economia' },
  { id: 'work', name: 'Trabalho e empresas' },
  { id: 'demography', name: 'Demografia' },
  { id: 'living', name: 'Condições de vida' },
  { id: 'education', name: 'Educação' },
] as const

export type IndicatorTheme = typeof indicatorThemes[number]['id']
export interface CatalogIndicator {
  code: string
  name: string
  theme: IndicatorTheme
  description: string
  source: string
  unit: string
  references: string
  note?: string
  format: (value: string) => string
}

const economy = { theme: 'economy', source: 'IBGE · PIB dos Municípios', unit: 'Reais (fonte em mil reais)', format: formatThousandReais, references: '2002–2021, anuais', note: 'Valores a preços correntes.' } as const
const work = { theme: 'work', source: 'IBGE · CEMPRE', references: '2022–2024, anuais', format: formatEducationCount, note: 'Organizações formais ativas abrangidas pelo CEMPRE, exceto MEIs. Não representa o conjunto dos moradores ou a economia informal. Série iniciada em 2022, com ruptura em relação à anterior.' } as const
const census = { source: 'IBGE · Censo Demográfico', references: '2022' }
const education = { theme: 'education', source: 'INEP · Censo Escolar', references: '2025', format: formatEducationCount } as const

export const indicatorCatalog: CatalogIndicator[] = [
  { ...economy, code: 'municipal_gdp', name: 'PIB', description: 'Produto Interno Bruto a preços correntes do município.', references: '2002–2023, anuais' },
  { ...economy, code: 'municipal_gdp_per_capita', name: 'PIB per capita', description: 'PIB por habitante, na série revisada publicada pelo IBGE. Não representa renda individual.', unit: 'Reais por habitante', references: '2010–2023, anuais', format: formatReais },
  { ...economy, code: 'agriculture_gva', name: 'VAB Agropecuária', description: 'Valor adicionado bruto da agropecuária a preços correntes.' },
  { ...economy, code: 'industry_gva', name: 'VAB Indústria', description: 'Valor adicionado bruto da indústria a preços correntes.' },
  { ...economy, code: 'services_gva', name: 'VAB Serviços', description: 'Valor adicionado bruto dos serviços, excluindo administração, defesa, educação e saúde públicas e seguridade social.' },
  { ...economy, code: 'public_administration_gva', name: 'VAB Administração pública', description: 'Valor adicionado bruto da administração, defesa, educação e saúde públicas e seguridade social.' },
  { ...work, code: 'cempre_local_units', name: 'Unidades locais', description: 'Locais de atuação das organizações, não entidades jurídicas distintas.', unit: 'Unidades' },
  { ...work, code: 'cempre_active_organizations', name: 'Empresas e organizações atuantes', description: 'Empresas e outras organizações com atuação no município. Não se limita a sedes.', unit: 'Unidades' },
  { ...work, code: 'cempre_employed_personnel', name: 'Pessoal ocupado total', description: 'Pessoal assalariado e sócios ou proprietários em atividade nas organizações.', unit: 'Pessoas' },
  { ...work, code: 'cempre_salaried_personnel', name: 'Pessoal ocupado assalariado', description: 'Pessoal assalariado segundo o CEMPRE. Não equivale automaticamente a empregados CLT.', unit: 'Pessoas' },
  { ...work, code: 'cempre_average_monthly_salary', name: 'Salário médio mensal', description: 'Média publicada para o pessoal assalariado das organizações. Não representa renda média dos moradores.', unit: 'Reais', format: formatReais },
  { ...census, theme: 'demography', code: 'population_census', name: 'População', description: 'População residente registrada no Censo.', unit: 'Pessoas', format: value => formatIndicator('population_census', value) },
  { ...census, theme: 'demography', code: 'territorial_area', name: 'Área territorial', description: 'Área da unidade territorial publicada pelo IBGE.', unit: 'km²', format: value => `${formatIndicator('territorial_area', value)} km²` },
  { ...census, theme: 'demography', code: 'population_density', name: 'Densidade demográfica', description: 'População residente por quilômetro quadrado.', unit: 'hab./km²', format: value => `${formatIndicator('population_density', value)} hab./km²` },
  ...demographicIndicators.map(item => ({ ...census, theme: 'demography' as const, code: item.code, name: item.label, description: item.description, unit: item.code === 'median_age' ? 'Anos' : 'Razão por 100', format: item.code === 'median_age' ? formatAge : formatRatio })),
  ...livingConditionIndicators.map(item => ({ ...census, theme: 'living' as const, code: item.code, name: item.label, description: item.description, unit: '%', format: formatPercentage, note: item.code === 'literacy_rate_15_plus' ? 'Pessoas residentes de 15 anos ou mais que sabem ler e escrever um bilhete simples. Não mede alfabetismo funcional.' : 'Domicílios particulares permanentes ocupados. A medida não avalia a qualidade ou o tratamento dos serviços.' })),
  ...schoolCensusIndicators.map(item => ({ ...education, code: item.code, name: item.label, description: item.description, unit: item.label, note: 'Educação básica, todas as dependências administrativas, áreas urbanas e rurais. Exclui turmas exclusivamente de atividade complementar e atendimento educacional especializado.' })),
  ...(['initial', 'final'] as const).map(stage => ({ theme: 'education' as const, code: `ideb_${stage}_years_public`, name: `IDEB — anos ${stage === 'initial' ? 'iniciais' : 'finais'} — rede pública`, description: 'Índice publicado pelo INEP que combina desempenho no Saeb e aprovação escolar.', source: 'INEP · IDEB', unit: 'Índice de 0 a 10', references: '2005–2025, edições bienais', format: formatIdeb, note: 'Rede pública do ensino fundamental regular; desempenho das escolas urbanas, conforme critérios de participação. Metodologia original, com ressalvas para a pandemia em 2021 e exclusão federal em 2011.' })),
]
