import { indicatorCatalog, indicatorThemes, type CatalogIndicator } from '../../utils/indicatorCatalog'

function IndicatorNavigation({ selected, onSelect }: { selected: CatalogIndicator, onSelect: (code: string) => void }) {
  return <div className="mt-8">
    <div role="group" aria-label="Temas" className="flex flex-wrap gap-2">
      {indicatorThemes.map(theme => <button key={theme.id} type="button" aria-pressed={selected.theme === theme.id} onClick={() => onSelect(indicatorCatalog.find(item => item.theme === theme.id)!.code)} className="min-h-11 rounded-md border border-border bg-surface px-4 py-2 text-sm text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary aria-pressed:border-navigation aria-pressed:font-semibold aria-pressed:text-navigation aria-pressed:underline aria-pressed:underline-offset-4">{theme.name}</button>)}
    </div>
    <div className="mt-5 max-w-xl">
      <label htmlFor="explorer-indicator" className="text-sm font-medium">Indicador</label>
      <select id="explorer-indicator" value={selected.code} onChange={event => onSelect(event.target.value)} className="mt-2 min-h-11 w-full min-w-0 rounded-md border border-border bg-surface px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
        {indicatorCatalog.filter(item => item.theme === selected.theme).map(item => <option key={item.code} value={item.code}>{item.name}</option>)}
      </select>
    </div>
  </div>
}

export default IndicatorNavigation
