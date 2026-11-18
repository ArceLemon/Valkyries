import type { ReactNode } from 'react'
import { cx } from '../lib/cx'

/** Panneau : regroupe un bloc d'information lié. */
export function Panneau({
  titre,
  description,
  actions,
  children,
  className,
}: {
  titre?: string
  description?: ReactNode
  actions?: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <section className={cx('rounded-panel border border-border bg-surface', className)}>
      {(titre || actions) && (
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
          <div>
            {titre && <h2 className="text-lg font-semibold text-text">{titre}</h2>}
            {description && <p className="mt-1 text-sm text-muted">{description}</p>}
          </div>
          {actions}
        </div>
      )}
      <div className="p-5">{children}</div>
    </section>
  )
}

/** Liste de définitions : libellé à gauche, valeur à droite. */
export function Dl({ elements }: { elements: [string, ReactNode][] }) {
  return (
    <dl className="divide-y divide-border">
      {elements.map(([libelle, valeur]) => (
        <div key={libelle} className="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[11rem_1fr] sm:gap-4">
          <dt className="text-sm text-muted">{libelle}</dt>
          <dd className="min-w-0 text-base text-text break-words">{valeur}</dd>
        </div>
      ))}
    </dl>
  )
}
