export function parseComparisonSelection(value: string | null) {
  const codes: number[] = []
  let invalid = false
  if (value === null || value === '') return { codes, invalid }
  for (const token of value.split(',')) {
    if (!/^[1-9][0-9]{6}$/.test(token) || codes.includes(Number(token)) || codes.length === 2) {
      invalid = true
      continue
    }
    codes.push(Number(token))
  }
  return { codes, invalid }
}

export function serializeComparisonSelection(codes: number[]): string {
  return parseComparisonSelection(codes.join(',')).codes.join(',')
}

export function normalizeComparisonSelection(value: string | null): string {
  return serializeComparisonSelection(parseComparisonSelection(value).codes)
}

export function selectComparisonMunicipality(codes: number[], index: 0 | 1, code: number): number[] {
  if (!/^[1-9][0-9]{6}$/.test(String(code)) || codes.some((existing, position) => position !== index && existing === code)) return codes
  const next = [...codes]
  next[Math.min(index, next.length)] = code
  return parseComparisonSelection(next.join(',')).codes
}

export function removeComparisonMunicipality(codes: number[], index: 0 | 1): number[] {
  return codes.filter((_, position) => position !== index)
}
