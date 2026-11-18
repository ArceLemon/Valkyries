import { useEffect, useRef, useState } from 'react'
import { api, messageErreur } from './api'

export interface EtatApi<T> {
  donnees: T | null
  erreur: string | null
  chargement: boolean
  recharger: () => void
}

/**
 * Charge une ressource de l'API. `intervalle` permet de rafraîchir automatiquement
 * tant que les données le demandent (par exemple une demande encore en attente) :
 * il reçoit les dernières données et renvoie un délai en millisecondes, ou null.
 */
export function useApi<T>(
  chemin: string | null,
  intervalle?: (donnees: T | null) => number | null,
): EtatApi<T> {
  const [donnees, setDonnees] = useState<T | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [chargement, setChargement] = useState(chemin !== null)
  const [version, setVersion] = useState(0)
  const intervalleRef = useRef(intervalle)

  useEffect(() => {
    intervalleRef.current = intervalle
  })

  useEffect(() => {
    if (chemin === null) return

    let actif = true
    let minuteur: ReturnType<typeof setTimeout> | undefined
    const controleur = new AbortController()

    const charger = async (premier: boolean) => {
      if (premier) setChargement(true)
      try {
        const resultat = await api<T>(chemin, { signal: controleur.signal })
        if (!actif) return
        setDonnees(resultat)
        setErreur(null)
        const delai = intervalleRef.current?.(resultat) ?? null
        if (delai) minuteur = setTimeout(() => void charger(false), delai)
      } catch (e) {
        if (!actif) return
        setErreur(messageErreur(e))
      } finally {
        if (actif && premier) setChargement(false)
      }
    }

    void charger(true)

    return () => {
      actif = false
      controleur.abort()
      if (minuteur) clearTimeout(minuteur)
    }
  }, [chemin, version])

  // Sans chemin (ressource désactivée), rien n'est à charger ni à montrer.
  return {
    donnees: chemin === null ? null : donnees,
    erreur: chemin === null ? null : erreur,
    chargement: chemin !== null && chargement,
    recharger: () => setVersion((v) => v + 1),
  }
}
