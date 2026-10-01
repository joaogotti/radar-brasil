import assert from 'node:assert/strict'
import test from 'node:test'
import { censusIndicator, demographicIndicators, livingConditionIndicators } from '../src/utils/demographicIndicators.ts'
import { formatAge, formatRatio, formatPercentage, formatPopulation } from '../src/utils/formatDemographicIndicator.ts'

test('seleciona os sete indicadores exclusivamente em 2022 sem mutar ou usar fallback', () => {
  const definitions = [...demographicIndicators, ...livingConditionIndicators]
  assert.equal(new Set(definitions.map(x => x.code)).size, 7)
  const data = Object.freeze(definitions.flatMap(({ code }) => [2023, 2022, 2021].map(reference_year => Object.freeze({ code, reference_year, value: '38.000000' }))))
  for (const { code } of definitions) {
    assert.equal(censusIndicator(data, code).reference_year, 2022)
    assert.equal(censusIndicator(data.filter(x => x.reference_year !== 2022), code), undefined)
    assert.equal(censusIndicator([], code), undefined)
  }
})

test('formata idade, razões acima de 100, percentuais e população sem confundir unidades', () => {
  assert.equal(formatAge('38.000000'), '38 anos')
  assert.equal(formatAge('38.500000'), '38,5 anos')
  assert.equal(formatRatio('85.200000'), '85,20')
  assert.equal(formatRatio('86.180000'), '86,18')
  assert.equal(formatRatio('100.850000'), '100,85')
  assert.equal(formatPercentage('97.760000'), '97,76%')
  assert.equal(formatPopulation(8801), '8.801 pessoas')
  assert.equal(formatPopulation(1), '1 pessoa')
})

test('zero não é ausência e strings decimais não perdem precisão antes da apresentação', () => {
  assert.equal(formatPercentage('0.000000'), '0,00%')
  assert.equal(formatPopulation(0), '0 pessoas')
  assert.equal(formatPercentage(null), 'Não disponível')
  assert.equal(formatPopulation(null), 'Não disponível')
  assert.equal(formatRatio('9007199254740993.01'), '9.007.199.254.740.993,01')
})
