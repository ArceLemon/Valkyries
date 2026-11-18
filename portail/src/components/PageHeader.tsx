import type { ReactNode } from 'react'
import { useTitre } from '../lib/useTitre'

export function PageHeader({
  titre,
  description,
  actions,
}: {
  titre: string
  description?: ReactNode
  actions?: ReactNode
}) {
  useTitre(titre)
  return (
    <header className="mb-6 flex flex-col gap-4 md:mb-8 md:flex-row md:items-start md:justify-between">
      <div className="min-w-0">
        <h1 className="text-2xl font-semibold text-text md:text-3xl">{titre}</h1>
        {description && <p className="mt-2 max-w-[65ch] text-base text-muted">{description}</p>}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-3">{actions}</div>}
    </header>
  )
}
