import { useCallback, useEffect, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { ValidationContexte } from '../lib/validationContexte'
import type { Valider } from '../lib/validationContexte'

type Etat = { phase: 'attente' | 'ok'; message: string } | null

// Assez long pour qu'on voie l'animation de chargement, même si l'API répond tout de suite.
const ATTENTE_MINIMALE = 700
const AFFICHAGE_VALIDE = 1700

/**
 * Fournit l'animation de validation à toute l'application. Elle s'affiche dans une boîte
 * de dialogue native : elle passe donc au-dessus des autres fenêtres déjà ouvertes.
 */
export function ValidationProvider({ children }: { children: ReactNode }) {
  const [etat, setEtat] = useState<Etat>(null)
  const dialogue = useRef<HTMLDialogElement>(null)
  const minuteur = useRef<number>(0)

  useEffect(() => {
    const d = dialogue.current
    if (!d) return
    if (etat && !d.open) d.showModal()
    if (!etat && d.open) d.close()
  }, [etat])

  const valider = useCallback<Valider>(async (travail, message) => {
    window.clearTimeout(minuteur.current)
    setEtat({ phase: 'attente', message })
    const debut = Date.now()
    try {
      const resultat = await travail
      const reste = ATTENTE_MINIMALE - (Date.now() - debut)
      if (reste > 0) await new Promise((fin) => setTimeout(fin, reste))
      setEtat({ phase: 'ok', message })
      minuteur.current = window.setTimeout(() => setEtat(null), AFFICHAGE_VALIDE)
      return resultat
    } catch (erreur) {
      setEtat(null)
      throw erreur
    }
  }, [])

  return (
    <ValidationContexte.Provider value={valider}>
      {children}
      <dialog ref={dialogue} className="valid-dialogue" aria-label="Validation" onCancel={(e) => e.preventDefault()}>
        {etat && (
          <div className="valid-carte" role="status" aria-live="polite">
            {etat.phase === 'attente' ? (
              <svg className="valid-icone" viewBox="0 0 96 96" aria-hidden="true">
                <circle cx="48" cy="48" r="36" fill="none" stroke="var(--accent-soft)" strokeWidth="9" />
                <circle
                  className="valid-spinner"
                  cx="48"
                  cy="48"
                  r="36"
                  fill="none"
                  stroke="var(--accent)"
                  strokeWidth="9"
                  strokeLinecap="round"
                  strokeDasharray="60 170"
                />
              </svg>
            ) : (
              <svg className="valid-icone" viewBox="0 0 96 96" aria-hidden="true">
                <circle className="valid-halo" cx="48" cy="48" r="36" fill="none" stroke="var(--ok)" strokeWidth="5" />
                <circle className="valid-fond" cx="48" cy="48" r="36" fill="var(--ok)" />
                <path
                  className="valid-coche"
                  d="M31 49l12 12 23-26"
                  fill="none"
                  stroke="#fff"
                  strokeWidth="8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            )}
            <p>{etat.phase === 'attente' ? 'Validation en cours…' : etat.message}</p>
          </div>
        )}
      </dialog>
    </ValidationContexte.Provider>
  )
}
