import { createHash } from 'node:crypto'
import { writeFile } from 'node:fs/promises'
import { geoArea } from 'd3-geo'

const meshUrl = 'https://servicodados.ibge.gov.br/api/v3/malhas/paises/BR?formato=application/vnd.geo%2Bjson&qualidade=intermediaria&intrarregiao=UF'

async function download(url) {
  const response = await fetch(url)
  if (!response.ok) throw new Error(`IBGE: HTTP ${response.status}`)
  return response.json()
}

const mesh = await download(meshUrl)

const features = mesh.features.map((feature) => {
  const code = String(feature.properties.codarea)
  if (!/^\d{2}$/.test(code)) throw new Error(`Invalid UF code: ${code}`)
  const geometry = feature.geometry
  if (!['Polygon', 'MultiPolygon'].includes(geometry.type)) {
    throw new Error(`Unexpected geometry: ${geometry.type}`)
  }
  const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates
  // D3 uses clockwise exterior rings for polygons smaller than a hemisphere.
  for (const coordinates of polygons) {
    if (geoArea({ type: 'Polygon', coordinates }) > 2 * Math.PI) {
      coordinates.forEach((ring) => ring.reverse())
    }
  }
  return {
    type: 'Feature',
    id: code,
    properties: { ibgeCode: code },
    geometry,
  }
})

if (features.length !== 27 || new Set(features.map((feature) => feature.id)).size !== 27) {
  throw new Error('Expected 27 unique UFs')
}
features.sort((a, b) => a.properties.ibgeCode.localeCompare(b.properties.ibgeCode))
const data = JSON.stringify({ type: 'FeatureCollection', features }) + '\n'
const directory = new URL('../src/assets/geography/', import.meta.url)
await writeFile(new URL('brazil-states.json', directory), data)
await writeFile(new URL('provenance.json', directory), JSON.stringify({
  provider: 'IBGE',
  meshUrl,
  retrievedAt: new Date().toISOString(),
  period: null,
  quality: 'intermediaria',
  sha256: createHash('sha256').update(data).digest('hex'),
}, null, 2) + '\n')
console.log(`Prepared ${features.length} UFs; ${Buffer.byteLength(data)} bytes`)
