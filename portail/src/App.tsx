import { lazy } from 'react'
import type { ComponentType } from 'react'
import { Route, Routes } from 'react-router-dom'
import { LayoutAuto, Racine, ReserveRoles, RouteProtegee } from './layout/Gardes'
import { Page404 } from './pages/Page404'
import { PageConnexion } from './pages/PageConnexion'

/**
 * Chaque page se charge à la demande : l'écran de connexion reste léger, et un
 * agent ne télécharge pas le code de l'administration.
 */
function page<M extends Record<string, unknown>>(charger: () => Promise<M>, nom: keyof M & string) {
  return lazy(async () => ({ default: (await charger())[nom] as ComponentType }))
}

const PageVerifier = page(() => import('./pages/PageVerifier'), 'PageVerifier')
const PageCatalogue = page(() => import('./pages/PageCatalogue'), 'PageCatalogue')

const TableauDeBord = page(() => import('./pages/agent/TableauDeBord'), 'TableauDeBord')
const MesDemandes = page(() => import('./pages/agent/MesDemandes'), 'MesDemandes')
const NouvelleDemande = page(() => import('./pages/agent/NouvelleDemande'), 'NouvelleDemande')
const DetailDemande = page(() => import('./pages/agent/DetailDemande'), 'DetailDemande')
const Demarches = page(() => import('./pages/agent/Demarches'), 'Demarches')

const Equipe = page(() => import('./pages/responsable/Equipe'), 'Equipe')
const Droits = page(() => import('./pages/responsable/Droits'), 'Droits')
const Cles = page(() => import('./pages/responsable/Cles'), 'Cles')
const JournalOrganisme = page(() => import('./pages/responsable/JournalOrganisme'), 'JournalOrganisme')

const MonRegistre = page(() => import('./pages/gestionnaire/MonRegistre'), 'MonRegistre')
const Acces = page(() => import('./pages/gestionnaire/Acces'), 'Acces')

const Supervision = page(() => import('./pages/admin/Supervision'), 'Supervision')
const Organismes = page(() => import('./pages/admin/Organismes'), 'Organismes')
const OrganismeDetail = page(() => import('./pages/admin/OrganismeDetail'), 'OrganismeDetail')
const Utilisateurs = page(() => import('./pages/admin/Utilisateurs'), 'Utilisateurs')
const DroitsAdmin = page(() => import('./pages/admin/DroitsAdmin'), 'DroitsAdmin')
const CatalogueAdmin = page(() => import('./pages/admin/CatalogueAdmin'), 'CatalogueAdmin')
const Journal = page(() => import('./pages/admin/Journal'), 'Journal')

const Accueil = page(() => import('./pages/citoyen/Accueil'), 'Accueil')
const AValider = page(() => import('./pages/citoyen/AValider'), 'AValider')
const Historique = page(() => import('./pages/citoyen/Historique'), 'Historique')
const Consentements = page(() => import('./pages/citoyen/Consentements'), 'Consentements')
const Preuves = page(() => import('./pages/citoyen/Preuves'), 'Preuves')

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Racine />} />
      <Route path="/connexion" element={<PageConnexion />} />

      {/* Pages ouvertes à tous : avec la coque de l'application si on est connecté, sinon version publique */}
      <Route element={<LayoutAuto />}>
        <Route path="/verifier" element={<PageVerifier />} />
        <Route path="/catalogue" element={<PageCatalogue />} />
      </Route>

      <Route element={<RouteProtegee />}>
        {/* Agent et responsable d'organisme */}
        <Route element={<ReserveRoles roles={['agent', 'responsable']} />}>
          <Route path="/tableau-de-bord" element={<TableauDeBord />} />
          <Route path="/demandes" element={<MesDemandes />} />
          <Route path="/demandes/nouvelle" element={<NouvelleDemande />} />
          <Route path="/demandes/:reference" element={<DetailDemande />} />
          <Route path="/demarches" element={<Demarches />} />
        </Route>

        {/* Responsable d'organisme */}
        <Route element={<ReserveRoles roles={['responsable']} />}>
          <Route path="/equipe" element={<Equipe />} />
          <Route path="/droits" element={<Droits />} />
          <Route path="/cles" element={<Cles />} />
          <Route path="/organisme/journal" element={<JournalOrganisme />} />
        </Route>

        {/* Gestionnaire de registre */}
        <Route element={<ReserveRoles roles={['gestionnaire']} />}>
          <Route path="/registre" element={<MonRegistre />} />
          <Route path="/registre/acces" element={<Acces />} />
        </Route>

        {/* Administrateur de la plateforme */}
        <Route element={<ReserveRoles roles={['administrateur']} />}>
          <Route path="/supervision" element={<Supervision />} />
          <Route path="/admin/organismes" element={<Organismes />} />
          <Route path="/admin/organismes/:id" element={<OrganismeDetail />} />
          <Route path="/admin/utilisateurs" element={<Utilisateurs />} />
          <Route path="/admin/droits" element={<DroitsAdmin />} />
          <Route path="/admin/catalogue" element={<CatalogueAdmin />} />
        </Route>

        {/* Administrateur et auditeur */}
        <Route element={<ReserveRoles roles={['administrateur', 'auditeur']} />}>
          <Route path="/journal" element={<Journal />} />
        </Route>

        {/* Citoyen */}
        <Route element={<ReserveRoles roles={['citoyen']} />}>
          <Route path="/mon-espace" element={<Accueil />} />
          <Route path="/mon-espace/a-valider" element={<AValider />} />
          <Route path="/mon-espace/historique" element={<Historique />} />
          <Route path="/mon-espace/consentements" element={<Consentements />} />
          <Route path="/mon-espace/preuves" element={<Preuves />} />
        </Route>
      </Route>

      <Route path="*" element={<Page404 />} />
    </Routes>
  )
}
