import { Route, Routes } from 'react-router'
import AppLayout from './components/layout/AppLayout'
import HomePage from './pages/HomePage'
import MunicipalitiesPage from './pages/MunicipalitiesPage'
import MunicipalityDetailPage from './pages/MunicipalityDetailPage'
import CompareMunicipalitiesPage from './pages/CompareMunicipalitiesPage'

function App() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="municipios" element={<MunicipalitiesPage />} />
        <Route path="municipios/:ibgeCode" element={<MunicipalityDetailPage />} />
        <Route path="comparar" element={<CompareMunicipalitiesPage />} />
      </Route>
    </Routes>
  )
}

export default App
