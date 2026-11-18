import { useEffect } from 'react'

/** Titre de l'onglet : utile pour s'y retrouver et pour les lecteurs d'écran. */
export function useTitre(titre: string): void {
  useEffect(() => {
    document.title = `${titre} | KUMA BANA`
  }, [titre])
}
