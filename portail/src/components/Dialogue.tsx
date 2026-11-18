import { X } from '@phosphor-icons/react'
import { useEffect, useRef } from 'react'
import type { ReactNode } from 'react'
import { Bouton } from './Bouton'

/**
 * Fenêtre modale basée sur l'élément natif <dialog> : le navigateur gère le piège
 * du focus, la touche Échap et l'inertie du reste de la page.
 */
export function Dialogue({
  ouvert,
  onFermer,
  titre,
  description,
  children,
  pied,
}: {
  ouvert: boolean
  onFermer: () => void
  titre: string
  description?: string
  children?: ReactNode
  pied?: ReactNode
}) {
  const ref = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialogue = ref.current
    if (!dialogue) return
    if (ouvert && !dialogue.open) dialogue.showModal()
    if (!ouvert && dialogue.open) dialogue.close()
  }, [ouvert])

  return (
    <dialog
      ref={ref}
      onClose={onFermer}
      aria-labelledby="dialogue-titre"
      className="m-auto w-[min(34rem,calc(100vw-2rem))] max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-panel border border-border bg-surface p-0 text-text backdrop:bg-black/50"
    >
      <div className="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
        <div>
          <h2 id="dialogue-titre" className="text-lg font-semibold">
            {titre}
          </h2>
          {description && <p className="mt-1 text-sm text-muted">{description}</p>}
        </div>
        <Bouton variante="discret" taille="s" onClick={onFermer} aria-label="Fermer" icone={<X size={18} />} />
      </div>
      {children && <div className="space-y-5 px-6 py-5">{children}</div>}
      {pied && <div className="flex flex-wrap justify-end gap-3 border-t border-border px-6 py-4">{pied}</div>}
    </dialog>
  )
}
