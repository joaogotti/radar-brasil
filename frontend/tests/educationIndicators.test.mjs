import assert from 'node:assert/strict'
import test from 'node:test'
import { schoolCensusIndicator, schoolCensusIndicators, idebEditions, idebSeries, idebHistory, latestIdebIndicator, continuousIdebSegments } from '../src/utils/educationIndicators.ts'
import { formatEducationCount, formatIdeb } from '../src/utils/formatEducationIndicator.ts'

const [ai, af] = idebSeries.map(x => x.code)
const item = (code, reference_year, value = '6.400000') => Object.freeze({ code, reference_year, value, name: code, unit: 'Índice' })

test('Censo seleciona os três indicadores exclusivamente em 2025, sem fallback ou mutação', () => {
  const data = Object.freeze(schoolCensusIndicators.flatMap(({ code }) => [item(code, 2023), item(code, 2025, '0.000000'), item(code, 2026)]))
  for (const { code } of schoolCensusIndicators) {
    assert.equal(schoolCensusIndicator(data, code).reference_year, 2025)
    assert.equal(schoolCensusIndicator(data, code).value, '0.000000')
    assert.equal(schoolCensusIndicator(data.filter(x => x.reference_year !== 2025), code), undefined)
  }
})

test('Censo parcial preserva os demais indicadores e ausência não vira zero', () => {
  const data = [item(schoolCensusIndicators[0].code, 2025, '77662.000000')]
  assert.equal(schoolCensusIndicator(data, schoolCensusIndicators[0].code).value, '77662.000000')
  for (const { code } of schoolCensusIndicators.slice(1)) assert.equal(schoolCensusIndicator(data, code), undefined)
})

test('últimos AI e AF são independentes; histórico crescente preserva strings e array original', () => {
  const data = Object.freeze([item(ai, 2025), item(af, 2021), item(ai, 2005), item(af, 2023, '5.000000')])
  const before = JSON.stringify(data)
  assert.equal(latestIdebIndicator(data, ai).reference_year, 2025)
  assert.equal(latestIdebIndicator(data, af).reference_year, 2023)
  assert.deepEqual(idebHistory(data, ai).map(x => x.reference_year), [2005, 2025])
  assert.equal(latestIdebIndicator(data, af).value, '5.000000')
  assert.equal(JSON.stringify(data), before)
})

test('calendário auditado bienal e série completa sem lacunas nos anos pares', () => {
  assert.deepEqual(idebEditions, [2005, 2007, 2009, 2011, 2013, 2015, 2017, 2019, 2021, 2023, 2025])
  for (const code of [ai, af]) {
    const data = Object.freeze([...idebEditions].reverse().map(year => item(code, year)))
    const history = idebHistory(data, code)
    assert.equal(history.length, 11)
    assert.deepEqual(continuousIdebSegments(history), [history])
    assert.deepEqual(idebHistory([item(code, 2024), item(code, 2027)], code), [])
  }
})

test('São Paulo: ausência de AI 2013 interrompe somente AI, sem interpolação', () => {
  const data = [2011, 2013, 2015].flatMap(year => [item(af, year), ...(year === 2013 ? [] : [item(ai, year)])])
  const years = code => continuousIdebSegments(idebHistory(data, code)).map(segment => segment.map(x => x.reference_year))
  assert.deepEqual(years(ai), [[2011], [2015]])
  assert.deepEqual(years(af), [[2011, 2013, 2015]])
})

test('Boa Esperança do Norte: apenas um ponto por série, sem segmento desenhável', () => {
  const data = [item(ai, 2025, '6.700000'), item(af, 2025, '5.000000')]
  for (const code of [ai, af]) {
    const history = idebHistory(data, code)
    assert.equal(history.length, 1)
    assert.deepEqual(continuousIdebSegments(history), [history])
    assert.equal(continuousIdebSegments(history).filter(x => x.length > 1).length, 0)
  }
})

test('somente AI, somente AF e nenhum IDEB não criam observações', () => {
  for (const code of [ai, af]) {
    const other = code === ai ? af : ai
    const data = [item(code, 2025, '0.000000')]
    assert.equal(latestIdebIndicator(data, code).value, '0.000000')
    assert.equal(latestIdebIndicator(data, other), undefined)
    assert.deepEqual(continuousIdebSegments(idebHistory(data, other)), [])
  }
  for (const code of [ai, af]) {
    assert.equal(latestIdebIndicator([], code), undefined)
    assert.deepEqual(idebHistory([], code), [])
  }
})

test('formatação pt-BR dos três totais e IDEB preserva zero e precisão original', () => {
  assert.equal(formatEducationCount('77662.000000'), '77.662')
  assert.equal(formatEducationCount('178.000000'), '178')
  assert.equal(formatEducationCount('5251.000000'), '5.251')
  assert.equal(formatIdeb('6.400000'), '6,4')
  assert.equal(formatIdeb('5.000000'), '5,0')
  assert.equal(formatIdeb('0.000000'), '0,0')
  assert.equal(formatEducationCount('0.000000'), '0')
  assert.equal(formatEducationCount('9007199254740993.000000'), '9.007.199.254.740.993')
})
