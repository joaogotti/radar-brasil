import { lazy } from 'react'
import { Route, Routes } from 'react-router'
import AppLayout from './components/layout/AppLayout'

const HomePage = lazy(() => import('./pages/HomePage'))
const MunicipalitiesPage = lazy(() => import('./pages/MunicipalitiesPage'))
const MunicipalityDetailPage = lazy(() => import('./pages/MunicipalityDetailPage'))
const CompareMunicipalitiesPage = lazy(() => import('./pages/CompareMunicipalitiesPage'))
const IndicatorsPage = lazy(() => import('./pages/IndicatorsPage'))
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'))

function App() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="indicadores" element={<IndicatorsPage />} />
        <Route path="municipios" element={<MunicipalitiesPage />} />
        <Route path="municipios/:ibgeCode" element={<MunicipalityDetailPage />} />
        <Route path="comparar" element={<CompareMunicipalitiesPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}

export default App
