import assert from 'node:assert/strict'
import test from 'node:test'
import { workAndBusinessIndicators } from '../src/utils/workAndBusinessIndicators.ts'

const codes = ['cempre_local_units', 'cempre_active_organizations', 'cempre_employed_personnel', 'cempre_salaried_personnel', 'cempre_average_monthly_salary']
const item = (code, reference_year, value = '1.000000') => Object.freeze({ code, reference_year, value, name: code, unit: code === codes[4] ? 'Reais' : 'Pessoas' })

test('seleciona o maior ano de cada indicador independentemente, sem fixar 2024 ou alterar a resposta', () => {
  const data = Object.freeze([
    item(codes[0], 2023), item(codes[0], 2026, '42.000000'), item(codes[0], 2022),
    item(codes[1], 2022), item(codes[2], 2025), item(codes[3], 2023),
    item(codes[4], 2022, '5375.960000'), item('municipal_gdp', 2030),
  ])
  const before = JSON.stringify(data)
  const cards = workAndBusinessIndicators(data)
  assert.deepEqual(cards.map(x => x.code), codes)
  assert.deepEqual(cards.map(x => x.indicator.reference_year), [2026, 2022, 2025, 2023, 2022])
  assert.equal(cards[0].indicator, data[1])
  assert.equal(cards[4].indicator.value, '5375.960000')
  assert.equal(JSON.stringify(data), before)
})

test('ausência mantém os cinco cards, sem zero e sem substituir por indicadores de outra fonte', () => {
  for (const data of [[], [item('population_census', 2022)]]) {
    const cards = workAndBusinessIndicators(data)
    assert.equal(cards.length, 5)
    assert.ok(cards.every(x => x.indicator === undefined))
  }
  const cards = workAndBusinessIndicators([item(codes[2], 2024)])
  assert.deepEqual(cards.map(x => Boolean(x.indicator)), [false, false, true, false, false])
})

test('zero válido permanece disponível e os formatadores reutilizados preservam a apresentação pt-BR', () => {
  const values = ['41791.000000', '40063.000000', '276540.000000', '222607.000000', '5375.960000']
  const cards = workAndBusinessIndicators(codes.map((code, index) => item(code, 2024, values[index])))
  assert.deepEqual(cards.map(x => x.format(x.indicator.value).replace(/\s/g, ' ')), ['41.791', '40.063', '276.540', '222.607', 'R$ 5.375,96'])
  for (const card of workAndBusinessIndicators(codes.map(code => item(code, 2025, '0.000000')))) {
    assert.equal(card.indicator.value, '0.000000')
    assert.equal(card.format(card.indicator.value).replace(/\s/g, ' '), card.code === codes[4] ? 'R$ 0,00' : '0')
  }
})
