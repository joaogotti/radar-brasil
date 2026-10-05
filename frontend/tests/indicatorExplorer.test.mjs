import assert from 'node:assert/strict'
import test from 'node:test'
import { indicatorCatalog, indicatorThemes } from '../src/utils/indicatorCatalog.ts'
import { hasMunicipalitySearchCriteria, indicatorObservations, parseIndicatorSelection, serializeIndicatorSelection } from '../src/utils/indicatorExplorer.ts'

test('busca vazia com todas as UFs não inicia listagem; nome ou UF permitem consultar', () => {
  for (const search of ['', ' ', '\t\n']) assert.equal(hasMunicipalitySearchCriteria(search, null), false)
  assert.equal(hasMunicipalitySearchCriteria(' Vitória ', null), true)
  assert.equal(hasMunicipalitySearchCriteria('', 32), true)
  assert.equal(hasMunicipalitySearchCriteria('   ', 32), true)
  assert.equal(hasMunicipalitySearchCriteria('Serra', 32), true)
})

const item = (code, reference_year, value = '1.000000') => Object.freeze({ code, reference_year, value, name: code, unit: 'Unidades' })

test('catálogo cobre exatamente os 26 códigos esperados em cinco temas', () => {
  const expected = {
    economy: ['municipal_gdp', 'municipal_gdp_per_capita', 'agriculture_gva', 'industry_gva', 'services_gva', 'public_administration_gva'],
    work: ['cempre_local_units', 'cempre_active_organizations', 'cempre_employed_personnel', 'cempre_salaried_personnel', 'cempre_average_monthly_salary'],
    demography: ['population_census', 'territorial_area', 'population_density', 'median_age', 'aging_index_65_plus', 'sex_ratio'],
    living: ['literacy_rate_15_plus', 'households_main_water_network_pct', 'households_sewer_stormwater_or_connected_septic_pct', 'households_collected_garbage_pct'],
    education: ['basic_education_enrollments', 'basic_education_establishments', 'basic_education_teachers', 'ideb_initial_years_public', 'ideb_final_years_public'],
  }
  assert.equal(indicatorCatalog.length, 26)
  assert.equal(new Set(indicatorCatalog.map(x => x.code)).size, 26)
  assert.deepEqual(indicatorThemes.map(x => x.id), Object.keys(expected))
  for (const [theme, codes] of Object.entries(expected)) assert.deepEqual(indicatorCatalog.filter(x => x.theme === theme).map(x => x.code), codes)
  for (const definition of indicatorCatalog) {
    assert.ok(definition.description && definition.source && definition.unit && definition.references)
    assert.equal(typeof definition.format('0.000000'), 'string')
  }
})

test('URL restaura indicador, tema e município; padrão não seleciona município', () => {
  const selection = parseIndicatorSelection(new URLSearchParams('indicador=cempre_local_units&municipio=3205309'))
  assert.equal(selection.indicator.code, 'cempre_local_units')
  assert.equal(selection.indicator.theme, 'work')
  assert.equal(selection.municipality, 3205309)
  assert.equal(selection.invalidIndicator, false)
  assert.equal(selection.invalidMunicipality, false)
  const empty = parseIndicatorSelection(new URLSearchParams())
  assert.equal(empty.municipality, null)
  assert.equal(empty.indicator.code, 'municipal_gdp')
  assert.equal(empty.invalidIndicator, false)
})

test('parâmetros inválidos e repetidos têm fallback explícito, sem código municipal parcial', () => {
  for (const query of ['indicador=unknown', 'indicador=', 'indicador=municipal_gdp&indicador=sex_ratio']) {
    const parsed = parseIndicatorSelection(new URLSearchParams(query))
    assert.equal(parsed.indicator.code, 'municipal_gdp')
    assert.equal(parsed.invalidIndicator, true)
  }
  for (const value of ['', '32', '3205309x', '3205309.0', '-3205309', '0000000', '32053090', '3205309&municipio=3550308']) {
    const parsed = parseIndicatorSelection(new URLSearchParams(`municipio=${value}`))
    assert.equal(parsed.municipality, null)
    assert.equal(parsed.invalidMunicipality, true)
  }
})

test('troca de indicador preserva município confirmado e tema deriva do novo indicador', () => {
  const initial = parseIndicatorSelection(new URLSearchParams('indicador=municipal_gdp&municipio=3205309'))
  const params = serializeIndicatorSelection('ideb_final_years_public', initial.municipality)
  assert.equal(params.toString(), 'indicador=ideb_final_years_public&municipio=3205309')
  const next = parseIndicatorSelection(params)
  assert.equal(next.municipality, initial.municipality)
  assert.equal(next.indicator.theme, 'education')
  assert.equal(serializeIndicatorSelection('municipal_gdp', null).has('municipio'), false)
})

test('histórico ordenado seleciona último resultado por código sem alterar dados nem preencher lacunas', () => {
  const data = Object.freeze([item('municipal_gdp', 2020, '28257606.000000'), item('municipal_gdp_per_capita', 2026), item('municipal_gdp', 2023), item('municipal_gdp', 2018)])
  const before = JSON.stringify(data)
  const history = indicatorObservations(data, 'municipal_gdp')
  assert.deepEqual(history.map(x => x.reference_year), [2023, 2020, 2018])
  assert.equal(history[0], data[2])
  assert.equal(indicatorObservations(data, 'municipal_gdp_per_capita')[0].reference_year, 2026)
  assert.equal(JSON.stringify(data), before)
  assert.deepEqual(indicatorObservations(data, 'median_age'), [])
  assert.deepEqual(indicatorObservations([], 'municipal_gdp'), [])
})

test('CEMPRE e IDEB selecionam referências independentes, sem limitar anos ou converter zero em ausência', () => {
  for (const code of ['cempre_average_monthly_salary', 'ideb_initial_years_public', 'ideb_final_years_public']) {
    const data = [item(code, 2022), item('population_census', 2035), item(code, 2027, '0.000000')]
    const history = indicatorObservations(data, code)
    assert.equal(history[0].reference_year, 2027)
    assert.equal(history[0].value, '0.000000')
    assert.equal(history.length, 2)
    assert.equal(indicatorObservations([data[0]], code).length, 1)
  }
})
