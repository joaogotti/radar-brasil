import { Route, Routes } from 'react-router'
import AppLayout from './components/layout/AppLayout'
import HomePage from './pages/HomePage'
import MunicipalitiesPage from './pages/MunicipalitiesPage'

function App() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="municipios" element={<MunicipalitiesPage />} />
      </Route>
    </Routes>
  )
}

export default App
