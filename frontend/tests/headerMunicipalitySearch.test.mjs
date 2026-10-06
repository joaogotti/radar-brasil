import test from 'node:test'
import assert from 'node:assert/strict'
import { headerMunicipalityResults } from '../src/utils/headerMunicipalitySearch.ts'

const municipalities = Object.freeze([
  { name: 'Porto Vitória', ibge_code: 4120309 },
  { name: 'Vitória Brasil', ibge_code: 3556958 },
  { name: 'Vitória', ibge_code: 3205309 },
].map(Object.freeze))

test('busca rápida prioriza nome exato e prefixo sem exigir acentos ou alterar a resposta', () => {
  assert.deepEqual(headerMunicipalityResults(municipalities, '  VITORIA ').map(item => item.ibge_code), [3205309, 3556958, 4120309])
  assert.equal(municipalities[0].ibge_code, 4120309)
})

test('busca rápida vazia não apresenta sugestões e a lista é limitada a seis resultados', () => {
  assert.deepEqual(headerMunicipalityResults(municipalities, '  '), [])
  assert.equal(headerMunicipalityResults(Array.from({ length: 20 }, (_, i) => ({ name: `Serra ${i}` })), 'Serra').length, 6)
})
