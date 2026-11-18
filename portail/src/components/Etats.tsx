import { Tray, WarningCircle } from '@phosphor-icons/react'
import type { Icon } from '@phosphor-icons/react'
import type { ReactNode } from 'react'
import { Bouton } from './Bouton'

/** Chargement : des formes qui annoncent le contenu, pas une roue qui tourne. */
export function Chargement({ lignes = 3 }: { lignes?: number }) {
  return (
    <div role="status" aria-live="polite">
      <span className="sr-only">Chargement en cours</span>
      <div className="space-y-3" aria-hidden="true">
        {Array.from({ length: lignes }, (_, i) => (
          <div key={i} className="h-14 animate-pulse rounded-control bg-surface-2" />
        ))}
      </div>
    </div>
  )
}

/** État vide : dit ce que l'écran affichera et comment le remplir. */
export function Vide({
  titre,
  texte,
  action,
  icone: Icone = Tray,
}: {
  titre: string
  texte?: ReactNode
  action?: ReactNode
  icone?: Icon
}) {
  return (
    <div className="flex flex-col items-center rounded-panel border border-dashed border-border-strong bg-surface px-6 py-12 text-center">
      <Icone size={32} className="text-muted" aria-hidden="true" />
      <h2 className="mt-4 text-lg font-semibold text-text">{titre}</h2>
      {texte && <p className="mt-2 max-w-[55ch] text-base text-muted">{texte}</p>}
      {action && <div className="mt-6">{action}</div>}
    </div>
  )
}

export function Erreur({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div
      role="alert"
      className="flex flex-col items-start gap-3 rounded-panel border border-danger bg-danger-soft p-5 text-danger-soft-text sm:flex-row sm:items-center"
    >
      <WarningCircle size={24} className="shrink-0" aria-hidden="true" />
      <p className="flex-1 text-base font-medium">{message}</p>
      {onRetry && (
        <Bouton variante="secondaire" taille="s" onClick={onRetry}>
          Réessayer
        </Bouton>
      )}
    </div>
  )
}

/** Affiche le bon état d'une ressource chargée par useApi. */
export function Ressource<T>({
  etat,
  children,
  lignes,
}: {
  etat: { donnees: T | null; erreur: string | null; chargement: boolean; recharger: () => void }
  children: (donnees: T) => ReactNode
  lignes?: number
}) {
  if (etat.donnees === null && etat.chargement) return <Chargement lignes={lignes} />
  if (etat.erreur && etat.donnees === null) {
    return <Erreur message={etat.erreur} onRetry={etat.recharger} />
  }
  if (etat.donnees === null) return null
  return <>{children(etat.donnees)}</>
}
