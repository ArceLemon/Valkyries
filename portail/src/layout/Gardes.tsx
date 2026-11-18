import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { Splash } from '../components/Splash'
import { useAuth } from '../lib/auth'
import { accueilDuRole } from '../lib/nav'
import type { Role } from '../lib/types'
import { LayoutPublic } from './LayoutPublic'
import { Shell } from './Shell'

/** Pendant que la session est retrouvée : le même écran de marque que le démarrage. */
function EcranDeChargement() {
  return <Splash message="Reconnexion en cours" />
}

/** Exige une session : sinon, retour à la connexion en mémorisant la page voulue. */
export function RouteProtegee() {
  const { utilisateur, chargement } = useAuth()
  const lieu = useLocation()

  if (chargement) return <EcranDeChargement />
  if (!utilisateur) {
    return <Navigate to="/connexion" replace state={{ depuis: lieu.pathname + lieu.search }} />
  }
  return <Shell />
}

/** Réserve un groupe de pages à certains rôles. */
export function ReserveRoles({ roles }: { roles: Role[] }) {
  const { utilisateur } = useAuth()
  if (!utilisateur) return null
  if (!roles.includes(utilisateur.role)) {
    return <Navigate to={accueilDuRole(utilisateur.role)} replace />
  }
  return <Outlet />
}

/** Page ouverte à tous : avec la coque de l'application si on est connecté, sinon en version publique. */
export function LayoutAuto() {
  const { utilisateur, chargement } = useAuth()
  if (chargement) return <EcranDeChargement />
  return utilisateur ? <Shell /> : <LayoutPublic />
}

/** « / » : redirige vers l'accueil du rôle, ou vers la connexion. */
export function Racine() {
  const { utilisateur, chargement } = useAuth()
  if (chargement) return <EcranDeChargement />
  return <Navigate to={utilisateur ? accueilDuRole(utilisateur.role) : '/connexion'} replace />
}
