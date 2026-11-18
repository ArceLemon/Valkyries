import { cx } from '../lib/cx'

export interface OptionSegment<T extends string> {
  valeur: T
  libelle: string
  compte?: number
}

/** Choix exclusif sous forme de boutons : filtres et onglets simples. */
export function Segmente<T extends string>({
  legende,
  valeur,
  options,
  onChange,
}: {
  legende: string
  valeur: T
  options: OptionSegment<T>[]
  onChange: (valeur: T) => void
}) {
  return (
    <div role="group" aria-label={legende} className="inline-flex flex-wrap gap-1 rounded-control border border-border bg-surface-2 p-1">
      {options.map((o) => {
        const actif = o.valeur === valeur
        return (
          <button
            key={o.valeur}
            type="button"
            aria-pressed={actif}
            onClick={() => onChange(o.valeur)}
            className={cx(
              'inline-flex min-h-9 items-center gap-2 rounded-control border px-3 text-sm font-medium transition-colors',
              actif
                ? 'border-border bg-surface text-text'
                : 'border-transparent text-muted hover:text-text',
            )}
          >
            {o.libelle}
            {o.compte !== undefined && <span className="text-muted">{o.compte}</span>}
          </button>
        )
      })}
    </div>
  )
}
