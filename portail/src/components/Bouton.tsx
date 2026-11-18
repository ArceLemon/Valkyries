import { CircleNotch } from '@phosphor-icons/react'
import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { cx } from '../lib/cx'

export type Variante = 'primaire' | 'secondaire' | 'discret' | 'danger'
export type Taille = 'm' | 's'

const base =
  'inline-flex items-center justify-center gap-2 rounded-control font-medium whitespace-nowrap ' +
  'transition-colors active:translate-y-px disabled:pointer-events-none disabled:opacity-50'

const tailles: Record<Taille, string> = {
  // 44 px : cible tactile confortable
  m: 'h-11 px-5 text-sm',
  s: 'h-9 px-3 text-sm',
}

const variantes: Record<Variante, string> = {
  primaire: 'bg-accent text-accent-contrast hover:bg-accent-hover',
  secondaire: 'border border-border-strong bg-surface text-text hover:bg-surface-2',
  discret: 'text-text hover:bg-surface-2',
  danger: 'bg-danger-soft-text text-accent-contrast hover:opacity-90',
}

/** Classes d'un bouton, pour styler aussi un lien (<Link>) comme un bouton. */
export function classesBouton(variante: Variante = 'primaire', taille: Taille = 'm'): string {
  return cx(base, tailles[taille], variantes[variante])
}

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: Variante
  taille?: Taille
  chargement?: boolean
  icone?: ReactNode
}

export function Bouton({
  variante = 'primaire',
  taille = 'm',
  chargement = false,
  icone,
  className,
  children,
  disabled,
  type = 'button',
  ...reste
}: Props) {
  return (
    <button
      type={type}
      disabled={disabled || chargement}
      aria-busy={chargement || undefined}
      className={cx(classesBouton(variante, taille), className)}
      {...reste}
    >
      {chargement ? <CircleNotch size={18} className="animate-spin" aria-hidden="true" /> : icone}
      {children}
    </button>
  )
}
