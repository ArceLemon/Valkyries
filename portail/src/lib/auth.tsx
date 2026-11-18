import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { api, ecrireJeton, EVENEMENT_SESSION_EXPIREE, lireJeton } from './api'
import type { Utilisateur } from './types'

interface Contexte {
  utilisateur: Utilisateur | null
  chargement: boolean
  connexion: (email: string, motDePasse: string) => Promise<Utilisateur>
  deconnexion: () => Promise<void>
}

const AuthContexte = createContext<Contexte | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [utilisateur, setUtilisateur] = useState<Utilisateur | null>(null)
  const [chargement, setChargement] = useState(lireJeton() !== null)

  // Au chargement : si un jeton existe, on retrouve l'utilisateur.
  useEffect(() => {
    if (lireJeton() === null) return
    let actif = true
    api<Utilisateur>('/auth/moi')
      .then((u) => actif && setUtilisateur(u))
      .catch(() => ecrireJeton(null))
      .finally(() => actif && setChargement(false))
    return () => {
      actif = false
    }
  }, [])

  // Jeton refusé par le serveur (expiré, compte révoqué) : retour à la connexion.
  useEffect(() => {
    const surExpiration = () => {
      ecrireJeton(null)
      setUtilisateur(null)
    }
    window.addEventListener(EVENEMENT_SESSION_EXPIREE, surExpiration)
    return () => window.removeEventListener(EVENEMENT_SESSION_EXPIREE, surExpiration)
  }, [])

  const connexion = useCallback(async (email: string, motDePasse: string) => {
    const reponse = await api<{ jeton: string; utilisateur: Utilisateur }>('/auth/connexion', {
      method: 'POST',
      // Un citoyen se connecte avec son identifiant (BF-0001), un agent avec son e-mail.
      corps: email.includes('@')
        ? { email, mot_de_passe: motDePasse }
        : { identifiant: email.toUpperCase(), mot_de_passe: motDePasse },
    })
    ecrireJeton(reponse.jeton)
    setUtilisateur(reponse.utilisateur)
    return reponse.utilisateur
  }, [])

  const deconnexion = useCallback(async () => {
    try {
      await api('/auth/deconnexion', { method: 'POST' })
    } catch {
      // Même si le serveur ne répond pas, on ferme la session ici.
    }
    ecrireJeton(null)
    setUtilisateur(null)
  }, [])

  const valeur = useMemo(
    () => ({ utilisateur, chargement, connexion, deconnexion }),
    [utilisateur, chargement, connexion, deconnexion],
  )

  return <AuthContexte.Provider value={valeur}>{children}</AuthContexte.Provider>
}

export function useAuth(): Contexte {
  const contexte = useContext(AuthContexte)
  if (contexte === null) throw new Error('useAuth doit être utilisé dans AuthProvider')
  return contexte
}
