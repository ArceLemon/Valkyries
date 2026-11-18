import { useEffect, useState } from 'react'
import type { ReactNode } from 'react'
import { Splash } from '../components/Splash'
import { useAuth } from '../lib/auth'

const CLE = 'kumabana.splash'
/** Durée minimale de l'écran de chargement : un vrai moment de marque, pas un clignotement. */
const DUREE_MINIMALE = 1900

function dejaVu(): boolean {
  try {
    return sessionStorage.getItem(CLE) === '1'
  } catch {
    return false
  }
}

/**
 * Affiche l'écran de chargement une fois par session, puis le retire en douceur
 * quand l'application est prête (session retrouvée) et que le temps minimal est écoulé.
 * L'application se prépare déjà derrière l'écran de chargement.
 */
export function Demarrage({ children }: { children: ReactNode }) {
  const { chargement } = useAuth()
  const [vu] = useState(dejaVu)
  const [tempsEcoule, setTempsEcoule] = useState(vu)
  const [affiche, setAffiche] = useState(!vu)

  useEffect(() => {
    if (vu) return
    const minuteur = setTimeout(() => {
      setTempsEcoule(true)
      try {
        sessionStorage.setItem(CLE, '1')
      } catch {
        // Sans stockage, l'écran réapparaîtra au prochain chargement : sans gravité.
      }
    }, DUREE_MINIMALE)
    return () => clearTimeout(minuteur)
  }, [vu])

  return (
    <>
      {children}
      {affiche && <Splash sortie={tempsEcoule && !chargement} onFini={() => setAffiche(false)} />}
    </>
  )
}
