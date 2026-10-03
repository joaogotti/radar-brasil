import assert from 'node:assert/strict'
import test from 'node:test'
import { comparisonGroups, comparisonValues, differentReferenceYears } from '../src/utils/comparisonIndicators.ts'

const item = (code, reference_year, value = '1.000000') => Object.freeze({ code, reference_year, value, name: code, unit: 'unidade' })
const definitions = comparisonGroups.flatMap(group => group.indicators)

test('cinco temas em ordem, exatamente 21 códigos distintos', () => {
  assert.deepEqual(comparisonGroups.map(group => group.title), ['Visão geral', 'Economia', 'Demografia', 'Condições de vida', 'Educação'])
  assert.equal(definitions.length, 21)
  assert.equal(new Set(definitions.map(item => item.code)).size, 21)
})

test('referências fixas: 2022 e Censo Escolar 2025, sem fallback', () => {
  for (const group of comparisonGroups.filter(group => group.id !== 'economy')) {
    for (const { code } of group.indicators.filter(item => !item.code.startsWith('ideb_'))) {
      const year = group.id === 'education' ? 2025 : 2022
      const data = [item(code, year - 1), item(code, year), item(code, year + 1)]
      assert.equal(comparisonValues(data)[code].reference_year, year)
      assert.equal(comparisonValues(data.filter(item => item.reference_year !== year))[code], undefined)
    }
  }
})

test('PIB, per capita e IDEB selecionam últimos independentes por município e etapa', () => {
  const a = comparisonValues([item('municipal_gdp', 2023), item('municipal_gdp_per_capita', 2021), item('ideb_initial_years_public', 2025), item('ideb_final_years_public', 2023)])
  const b = comparisonValues([item('municipal_gdp', 2021), item('municipal_gdp_per_capita', 2023), item('ideb_initial_years_public', 2021), item('ideb_final_years_public', 2025)])
  assert.equal(a.municipal_gdp.reference_year, 2023)
  assert.equal(a.municipal_gdp_per_capita.reference_year, 2021)
  assert.equal(a.ideb_initial_years_public.reference_year, 2025)
  assert.equal(a.ideb_final_years_public.reference_year, 2023)
  assert.equal(b.ideb_initial_years_public.reference_year, 2021)
  assert.equal(b.ideb_final_years_public.reference_year, 2025)
  assert.deepEqual(differentReferenceYears(a.municipal_gdp, b.municipal_gdp), [2023, 2021])
  assert.deepEqual(differentReferenceYears(a.municipal_gdp, b.municipal_gdp_per_capita), [])
  assert.deepEqual(differentReferenceYears(a.municipal_gdp, undefined), [])
})

test('VAB exige interseção dos quatro setores interna a cada município', () => {
  const codes = ['agriculture_gva', 'industry_gva', 'services_gva', 'public_administration_gva']
  const a = codes.flatMap((code, index) => [item(code, index === 0 ? 2023 : 2022), item(code, 2021)])
  const b = codes.map(code => item(code, 2019))
  for (const code of codes) {
    assert.equal(comparisonValues(a)[code].reference_year, 2021)
    assert.equal(comparisonValues(b)[code].reference_year, 2019)
    assert.equal(comparisonValues(b.slice(1))[code], undefined)
  }
})

test('ausência não vira zero; valores originais e array congelado são preservados', () => {
  const data = Object.freeze([item('population_census', 2022, '0.000000'), item('municipal_gdp', 2023, '28257606.000000')])
  const before = JSON.stringify(data)
  const result = comparisonValues(data)
  assert.equal(result.population_census.value, '0.000000')
  assert.equal(result.municipal_gdp, data[1])
  assert.equal(result.municipal_gdp_per_capita, undefined)
  assert.ok(Object.values(comparisonValues([])).every(item => item === undefined))
  assert.equal(JSON.stringify(data), before)
})

test('Boa Esperança do Norte: somente cinco indicadores educacionais disponíveis', () => {
  const data = comparisonGroups.at(-1).indicators.map((definition, index) => item(definition.code, 2025, ['1735.000000', '7.000000', '81.000000', '6.700000', '5.000000'][index]))
  const values = comparisonValues(data)
  assert.equal(Object.values(values).filter(Boolean).length, 5)
  assert.equal(values.population_census, undefined)
  assert.equal(values.municipal_gdp, undefined)
  assert.equal(values.basic_education_enrollments.value, '1735.000000')
})

test('cada unidade reutiliza seu formatter adequado', () => {
  const format = (code, value) => definitions.find(item => item.code === code).format(value).replace(/\s/g, ' ')
  assert.equal(format('population_census', '322869.000000'), '322.869')
  assert.equal(format('territorial_area', '97.123000'), '97,123')
  assert.equal(format('population_density', '3324.330000'), '3.324,33')
  assert.equal(format('municipal_gdp', '28257606.000000'), 'R$ 28,3 bi')
  assert.equal(format('municipal_gdp_per_capita', '87520.350000'), 'R$ 87.520,35')
  assert.equal(format('median_age', '38.000000'), '38 anos')
  assert.equal(format('sex_ratio', '86.180000'), '86,18')
  assert.equal(format('literacy_rate_15_plus', '97.760000'), '97,76%')
  assert.equal(format('basic_education_enrollments', '1735.000000'), '1.735')
  assert.equal(format('ideb_final_years_public', '5.000000'), '5,0')
})
