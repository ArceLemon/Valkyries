import type { Icon } from '@phosphor-icons/react'
import type { ReactNode } from 'react'
import { cx } from '../lib/cx'

export type TonStat = 'bleu' | 'jaune' | 'vert' | 'rouge'

const tons: Record<TonStat, string> = {
  bleu: 'bg-accent-soft text-accent-soft-text',
  jaune: 'bg-warn-soft text-warn-soft-text',
  vert: 'bg-ok-soft text-ok-soft-text',
  rouge: 'bg-danger-soft text-danger-soft-text',
}

/** Une grande valeur, avec une pastille de couleur qui dit de quoi il s'agit. */
export function Statistique({
  libelle,
  valeur,
  ton,
  icone: Icone,
}: {
  libelle: string
  valeur: ReactNode
  ton: TonStat
  icone: Icon
}) {
  return (
    <div className="flex items-center gap-4 rounded-panel border border-border bg-surface p-5">
      <span className={cx('grid size-14 shrink-0 place-items-center rounded-panel', tons[ton])}>
        <Icone size={30} weight="duotone" aria-hidden="true" />
      </span>
      <div className="min-w-0">
        <dt className="text-sm text-muted">{libelle}</dt>
        <dd className="mt-0.5 text-3xl font-semibold text-text">{valeur}</dd>
      </div>
    </div>
  )
}

export function GrilleStatistiques({ children }: { children: ReactNode }) {
  return <dl className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">{children}</dl>
}
