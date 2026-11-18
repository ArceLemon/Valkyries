import { CheckCircle, Info, Warning, WarningCircle } from '@phosphor-icons/react'
import type { Icon } from '@phosphor-icons/react'
import type { ReactNode, Ref } from 'react'
import { cx } from '../lib/cx'

type TonNotice = 'info' | 'succes' | 'attention' | 'erreur'

const styles: Record<TonNotice, { classes: string; icone: Icon }> = {
  info: { classes: 'border-border bg-accent-soft text-accent-soft-text', icone: Info },
  succes: { classes: 'border-ok bg-ok-soft text-ok-soft-text', icone: CheckCircle },
  attention: { classes: 'border-border bg-warn-soft text-warn-soft-text', icone: Warning },
  erreur: { classes: 'border-danger bg-danger-soft text-danger-soft-text', icone: WarningCircle },
}

/** Message annoncé aux lecteurs d'écran (alert pour une erreur, status sinon). */
export function Notice({
  ton = 'info',
  titre,
  children,
  className,
  ref,
}: {
  ton?: TonNotice
  titre?: string
  children: ReactNode
  className?: string
  ref?: Ref<HTMLDivElement>
}) {
  const { classes, icone: Icone } = styles[ton]
  return (
    <div
      ref={ref}
      tabIndex={-1}
      role={ton === 'erreur' || ton === 'attention' ? 'alert' : 'status'}
      className={cx('flex gap-3 rounded-panel border p-4 outline-none', classes, className)}
    >
      <Icone size={22} className="mt-0.5 shrink-0" aria-hidden="true" />
      <div className="min-w-0 text-base">
        {titre && <p className="font-semibold">{titre}</p>}
        <div className={titre ? 'mt-1' : undefined}>{children}</div>
      </div>
    </div>
  )
}
