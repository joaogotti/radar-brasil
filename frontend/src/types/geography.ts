import type { FeatureCollection, MultiPolygon, Polygon } from 'geojson'

export interface StateProperties {
  ibgeCode: string
}

export type BrazilStatesCollection = FeatureCollection<Polygon | MultiPolygon, StateProperties>
