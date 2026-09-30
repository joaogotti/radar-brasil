import assert from 'node:assert/strict'
import test from 'node:test'
import { continuousGdpSegments, economicComposition, gdpHistory, latestEconomicIndicator } from '../src/utils/economicIndicators.ts'
import { formatReais, formatThousandReais } from '../src/utils/formatEconomicIndicator.ts'

const item = (code, year, value = '1.000000') => ({ code, name: code, unit: code === 'municipal_gdp_per_capita' ? 'R$' : 'Mil Reais', reference_year: year, value })
const normalized = (value) => value.replace(/\s/g, ' ')

test('PIB e PIB per capita selecionam o último ano independentemente, sem mutar os dados', () => {
  const data = Object.freeze([
    item('municipal_gdp', 2022), item('municipal_gdp_per_capita', 2020),
    item('municipal_gdp', 2024), item('municipal_gdp_per_capita', 2021), item('municipal_gdp', 2002),
  ].map(Object.freeze))
  assert.equal(latestEconomicIndicator(data, 'municipal_gdp').reference_year, 2024)
  assert.equal(latestEconomicIndicator(data, 'municipal_gdp_per_capita').reference_year, 2021)
  assert.deepEqual(gdpHistory(data).map(x => x.reference_year), [2002, 2022, 2024])
  assert.equal(data[0].reference_year, 2022)
})

test('composição encontra a interseção real, mesmo quando o menor máximo é um ano ausente', () => {
  const data = [item('agriculture_gva', 2023), item('agriculture_gva', 2021)]
  for (const code of ['industry_gva', 'services_gva', 'public_administration_gva']) {
    data.push(item(code, 2022), item(code, 2021))
  }
  const composition = economicComposition(data)
  assert.equal(composition.year, 2021)
  assert.equal(composition.sectors.length, 4)
  assert.ok(composition.sectors.every(x => x.indicator.reference_year === 2021))
})

test('composição sem ano comum ou com setor ausente não inventa valores', () => {
  const data = ['agriculture_gva', 'industry_gva', 'services_gva', 'public_administration_gva'].map((code, index) => item(code, 2020 + index))
  assert.equal(economicComposition(data), null)
  assert.equal(economicComposition(data.slice(0, 3)), null)
})

test('histórico é crescente e a linha é interrompida em toda lacuna', () => {
  const history = gdpHistory([item('municipal_gdp', 2006), item('municipal_gdp', 2002), item('municipal_gdp', 2003), item('municipal_gdp', 2008)])
  assert.deepEqual(continuousGdpSegments(history).map(s => s.map(x => x.reference_year)), [[2002, 2003], [2006], [2008]])
  assert.equal(history.length, 4)
})

test('ausência e série com um único ponto são preservadas', () => {
  assert.equal(latestEconomicIndicator([], 'municipal_gdp'), undefined)
  assert.equal(latestEconomicIndicator([item('municipal_gdp', 2023)], 'municipal_gdp_per_capita'), undefined)
  assert.equal(economicComposition([]), null)
  assert.deepEqual(gdpHistory([]), [])
  assert.deepEqual(continuousGdpSegments([]), [])
  const single = [item('municipal_gdp', 2018)]
  assert.deepEqual(continuousGdpSegments(gdpHistory(single)), [single])
})

test('Mil Reais usa a escala correta em bilhões, milhões e valores menores', () => {
  assert.equal(normalized(formatThousandReais('28257606.000000')), 'R$ 28,3 bi')
  assert.equal(normalized(formatThousandReais('9518403')), 'R$ 9,5 bi')
  assert.equal(normalized(formatThousandReais('26154')), 'R$ 26,2 mi')
  assert.equal(normalized(formatThousandReais('950000.000000')), 'R$ 950 mi')
  assert.equal(normalized(formatThousandReais('1.234567')), 'R$ 1.234,57')
  assert.equal(normalized(formatThousandReais('28257606.000000', false)), 'R$ 28.257.606.000,00')
})

test('reais, zero e negativos são formatados sem aplicar a escala de Mil Reais indevidamente', () => {
  assert.equal(normalized(formatReais('87520.350000')), 'R$ 87.520,35')
  assert.equal(normalized(formatThousandReais('0.000000')), 'R$ 0,00')
  assert.equal(normalized(formatThousandReais('-19046.000000')), 'R$ -19 mi')
  assert.equal(normalized(formatThousandReais('-0.012345', false)), '-R$ 12,35')
})

test('formatação mantém a precisão de strings maiores que o limite inteiro do Number', () => {
  assert.equal(normalized(formatReais('9007199254740993.010000')), 'R$ 9.007.199.254.740.993,01')
  assert.equal(normalized(formatThousandReais('9007199254740993.010000', false)), 'R$ 9.007.199.254.740.993.010,00')
})
