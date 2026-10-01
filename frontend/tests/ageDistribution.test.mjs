import assert from 'node:assert/strict'
import test from 'node:test'
import { parseAgeDistribution, ageDistributionRows, percentageGeometry } from '../src/utils/ageDistribution.ts'

function fixture() {
  const codes = [93070, 93084, 93085, 93086, 93087, 93088, 93089, 93090, 93091, 93092, 93093, 93094, 93095, 93096, 93097, 93098, 49108, 49109, 60040, 60041, 6653]
  return {
    municipality: { ibge_code: 3205309, name: 'Vitória', state: { ibge_code: 32, name: 'Espírito Santo', abbreviation: 'ES' } },
    reference_year: 2022, availability: 'complete',
    source: { name: 'IBGE - Censo Demográfico', aggregate_id: 9514, population_variable_id: 93, percentage_variable_id: 1000093, percentage_basis: 'total_municipal_population' },
    data: codes.flatMap((ibge_code, index) => [4, 5].map(sex_code => ({
      sex_code, sex_label: sex_code === 4 ? 'Homens' : 'Mulheres',
      age_group: { ibge_code, label: index === 20 ? '100 anos ou mais' : `${index * 5} a ${index * 5 + 4} anos`, order: index + 1, min_age: index * 5, max_age: index === 20 ? null : index * 5 + 4 },
      population: 100, population_status: 'available', percentage: '2.000000', percentage_status: 'available',
    }))),
  }
}

test('contrato completo válido, pareamento e ordenação decrescente independentes da ordem da API', () => {
  const payload = fixture()
  payload.data.reverse()
  const snapshot = JSON.stringify(payload)
  const result = parseAgeDistribution(payload, 3205309)
  const rows = ageDistributionRows(result.data)
  assert.equal(result.availability, 'complete')
  assert.equal(rows.length, 21)
  assert.deepEqual(rows.map(x => x.age.order), Array.from({ length: 21 }, (_, i) => 21 - i))
  assert.ok(rows.every(x => x.men.sex_code === 4 && x.women.sex_code === 5))
  assert.equal(JSON.stringify(payload), snapshot)
})

test('rejeita contratos, unidades de referência, municípios, duplicações e faixas inconsistentes', () => {
  for (const change of [
    x => { x.reference_year = 2023 }, x => { x.municipality.ibge_code = 3550308 },
    x => { x.source.percentage_basis = 'sex_population' }, x => { x.source.aggregate_id = 1 },
    x => { x.data.push(x.data[0]) }, x => { x.data[1].age_group.order = 2 },
    x => { x.data[0].age_group.max_age = 10 }, x => { x.data[0].age_group.label = '' },
    x => { x.data[0].sex_code = 6 }, x => { x.data[0].sex_label = 'Mulheres' },
    x => { x.data[0].percentage = 2 }, x => { x.data[0].percentage = '100.000001' },
    x => { x.data[0].population = 1.5 }, x => { x.data[0].population = -1 },
    x => { x.data[0].percentage_status = 'unknown' }, x => { x.data[0].percentage_status = 'omitted' },
    x => { x.data[0].population = null }, x => { x.availability = 'unavailable' },
  ]) {
    const payload = fixture()
    change(payload)
    assert.throws(() => parseAgeDistribution(payload, 3205309))
  }
  for (const value of [null, [], {}, 'invalid']) assert.throws(() => parseAgeDistribution(value, 3205309))
})

test('partial aceita medidas independentes e células faltantes sem fabricar valores', () => {
  const payload = fixture()
  payload.availability = 'partial'
  payload.data[0].percentage = null
  payload.data[0].percentage_status = 'suppressed'
  payload.data[1].population = null
  payload.data[1].population_status = 'unavailable'
  payload.data.pop()
  const result = parseAgeDistribution(payload, 3205309)
  assert.equal(percentageGeometry(result.data).length(result.data[0], 100), null)
  assert.equal(result.data[0].population, 100)
  assert.equal(result.data[1].percentage, '2.000000')
  assert.equal(ageDistributionRows(result.data)[0].women, undefined)
})

test('Boa Esperança do Norte: 42 células omitted, null e unavailable; sem carga é distinto', () => {
  const payload = fixture()
  payload.municipality.ibge_code = 5101837
  payload.municipality.name = 'Boa Esperança do Norte'
  payload.municipality.state = { ibge_code: 51, name: 'Mato Grosso', abbreviation: 'MT' }
  payload.availability = 'unavailable'
  payload.data.forEach(x => { x.population = null; x.percentage = null; x.population_status = 'omitted'; x.percentage_status = 'omitted' })
  const result = parseAgeDistribution(payload, 5101837)
  assert.equal(result.data.length, 42)
  assert.equal(percentageGeometry(result.data).hasPercentages, false)
  payload.data = []
  payload.availability = 'not_loaded'
  assert.equal(parseAgeDistribution(payload, 5101837).availability, 'not_loaded')
})

test('escala comum preserva homens positivos, soma 100.01 e zero com população positiva', () => {
  const payload = fixture()
  payload.data.forEach(x => { x.percentage = '0.000000' })
  payload.data[0].percentage = '40.000000'
  payload.data[1].percentage = '60.010000'
  payload.data[40].population = 337
  const snapshot = JSON.stringify(payload)
  const data = parseAgeDistribution(payload, 3205309).data
  const geometry = percentageGeometry(data)
  assert.equal(geometry.domain, 61)
  assert.ok(Math.abs(geometry.length(data[0], 610) - 400) < 1e-9)
  assert.ok(Math.abs(geometry.length(data[1], 610) - 600.1) < 1e-9)
  assert.equal(geometry.length(data[40], 610), 0)
  assert.equal(data[40].population, 337)
  assert.equal(data.reduce((sum, x) => sum + BigInt(x.percentage.replace('.', '')), 0n), 100010000n)
  assert.equal(JSON.stringify(payload), snapshot)
})

test('domínio seguro com zeros; populações sem percentuais continuam textualmente disponíveis', () => {
  const payload = fixture()
  payload.data.forEach(x => { x.percentage = '0.000000' })
  const zero = percentageGeometry(payload.data)
  assert.equal(zero.domain, 1)
  assert.equal(zero.hasPercentages, true)
  assert.ok(payload.data.every(x => zero.length(x, 300) === 0))
  payload.availability = 'partial'
  payload.data.forEach(x => { x.percentage = null; x.percentage_status = 'not_applicable' })
  const result = parseAgeDistribution(payload, 3205309)
  assert.equal(percentageGeometry(result.data).hasPercentages, false)
  assert.ok(result.data.every(x => x.population === 100))
})
