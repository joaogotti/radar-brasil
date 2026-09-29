import Hero from './components/home/Hero'
import Header from './components/layout/Header'

function App() {
  return (
    <div className="flex min-h-dvh flex-col">
      <Header />
      <main className="flex-1">
        <Hero />
      </main>
    </div>
  )
}

export default App
