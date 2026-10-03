import assert from 'node:assert/strict'
import test from 'node:test'
import { parseComparisonSelection, normalizeComparisonSelection, serializeComparisonSelection, selectComparisonMunicipality, removeComparisonMunicipality } from '../src/utils/comparisonSelection.ts'

test('URL vazia, parsing e serialização preservam ordem A/B', () => {
  assert.deepEqual(parseComparisonSelection(null), { codes: [], invalid: false })
  assert.deepEqual(parseComparisonSelection(''), { codes: [], invalid: false })
  const codes = [3205309, 3205200]
  assert.deepEqual(parseComparisonSelection(serializeComparisonSelection(codes)), { codes, invalid: false })
  assert.equal(serializeComparisonSelection([...codes].reverse()), '3205200,3205309')
})

test('tokens inválidos, duplicados e excesso não descartam municípios válidos', () => {
  for (const token of ['bad', '320530', '32053090', '3.205309', '0000001', '-3205309', ' 3205309']) {
    assert.deepEqual(parseComparisonSelection(`${token},3205200`), { codes: [3205200], invalid: true })
  }
  assert.deepEqual(parseComparisonSelection('3205309,3205309,3205200,3205002'), { codes: [3205309, 3205200], invalid: true })
  assert.equal(normalizeComparisonSelection('bad,3205309,3205309,3205200'), '3205309,3205200')
  assert.equal(normalizeComparisonSelection(normalizeComparisonSelection('bad,3205309')), '3205309')
})

test('seleção, substituição, remoção e compactação não mutam a entrada', () => {
  const initial = Object.freeze([3205309, 3205200])
  assert.deepEqual(selectComparisonMunicipality([], 1, 3205309), [3205309])
  assert.deepEqual(selectComparisonMunicipality(initial, 1, 3205002), [3205309, 3205002])
  assert.deepEqual(selectComparisonMunicipality(initial, 1, 3205309), initial)
  assert.deepEqual(selectComparisonMunicipality(initial, 0, 1), initial)
  assert.deepEqual(removeComparisonMunicipality(initial, 0), [3205200])
  assert.deepEqual(removeComparisonMunicipality(initial, 1), [3205309])
  assert.equal(serializeComparisonSelection(removeComparisonMunicipality([3205309], 0)), '')
  assert.deepEqual(initial, [3205309, 3205200])
})
