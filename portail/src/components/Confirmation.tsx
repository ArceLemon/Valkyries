import type { ReactNode } from 'react'
import { Bouton } from './Bouton'
import { Dialogue } from './Dialogue'

/** Confirmation avant une action irréversible : le bouton nomme l'action, pas « OK ». */
export function Confirmation({
  ouvert,
  titre,
  children,
  libelleAction,
  danger = false,
  envoi = false,
  onConfirmer,
  onFermer,
}: {
  ouvert: boolean
  titre: string
  children: ReactNode
  libelleAction: string
  danger?: boolean
  envoi?: boolean
  onConfirmer: () => void
  onFermer: () => void
}) {
  return (
    <Dialogue
      ouvert={ouvert}
      onFermer={onFermer}
      titre={titre}
      pied={
        <>
          <Bouton variante="secondaire" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton variante={danger ? 'danger' : 'primaire'} onClick={onConfirmer} chargement={envoi}>
            {libelleAction}
          </Bouton>
        </>
      }
    >
      <div className="text-base text-text">{children}</div>
    </Dialogue>
  )
}
