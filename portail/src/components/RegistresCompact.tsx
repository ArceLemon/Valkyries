import { CheckCircle, Hourglass, XCircle } from '@phosphor-icons/react'
import { cx } from '../lib/cx'
import { libelleRegistre } from '../lib/format'
import type { Registre } from '../lib/types'

const ETAT = {
  en_service: { Icone: CheckCircle, texte: 'En service', classes: 'bg-ok-soft text-ok-soft-text', point: 'bg-ok' },
  lent: { Icone: Hourglass, texte: 'Lent', classes: 'bg-warn-soft text-warn-soft-text', point: 'bg-warn' },
  panne: { Icone: XCircle, texte: 'En panne', classes: 'bg-danger-soft text-danger-soft-text', point: 'bg-danger' },
} as const

/** État des registres en un coup d'œil : un voyant, un nom, un temps de réponse. */
export function RegistresCompact({ registres }: { registres: Registre[] }) {
  return (
    <ul className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
      {registres.map((r) => {
        const e = ETAT[r.etat as keyof typeof ETAT] ?? ETAT.panne
        return (
          <li key={r.registre} className="rounded-panel border border-border bg-bg p-4">
            <div className="flex items-center justify-between gap-2">
              <span className={cx('size-3 rounded-full', e.point, r.etat !== 'en_service' && 'animate-pulse')} aria-hidden="true" />
              <span className={cx('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold', e.classes)}>
                <e.Icone size={14} weight="bold" aria-hidden="true" />
                {e.texte}
              </span>
            </div>
            <p className="mt-3 text-sm font-medium text-text">{libelleRegistre(r.registre)}</p>
            <p className="mt-0.5 text-2xl font-bold text-text">
              {r.latence_ms ?? '-'}
              <span className="ml-1 text-sm font-semibold text-muted">ms</span>
            </p>
          </li>
        )
      })}
    </ul>
  )
}
