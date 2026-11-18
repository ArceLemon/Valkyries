import {
  Buildings,
  BookOpenText,
  Certificate,
  ClockCounterClockwise,
  Database,
  Eye,
  Handshake,
  House,
  Key,
  ListChecks,
  PlusCircle,
  Pulse,
  Scales,
  SealCheck,
  SquaresFour,
  TreeStructure,
  UsersThree,
  Users,
  BellRinging,
} from '@phosphor-icons/react'
import type { Icon } from '@phosphor-icons/react'
import type { Role } from './types'

export interface LienNav {
  chemin: string
  libelle: string
  icone: Icon
  /** Le lien n'est actif que sur son chemin exact (évite deux liens actifs à la fois). */
  exact?: boolean
  /** Libellé court pour la barre de navigation du bas sur mobile. */
  court?: string
}

export interface GroupeNav {
  titre?: string
  liens: LienNav[]
}

const catalogue: LienNav = { chemin: '/catalogue', libelle: 'Catalogue', icone: BookOpenText }

const liensDemandeur: LienNav[] = [
  { chemin: '/tableau-de-bord', libelle: 'Tableau de bord', icone: SquaresFour },
  { chemin: '/demandes/nouvelle', libelle: 'Nouvelle demande', icone: PlusCircle },
  { chemin: '/demandes', libelle: 'Mes demandes', icone: ListChecks, exact: true },
  { chemin: '/demarches', libelle: 'Démarches', icone: TreeStructure },
  { chemin: '/verifier', libelle: 'Vérifier une preuve', icone: SealCheck },
  catalogue,
]

export function navigationDuRole(role: Role): GroupeNav[] {
  switch (role) {
    case 'citoyen':
      return [
        {
          liens: [
            { chemin: '/mon-espace', libelle: 'Accueil', icone: House, exact: true },
            { chemin: '/mon-espace/a-valider', libelle: 'À valider', icone: BellRinging },
            { chemin: '/mon-espace/historique', libelle: 'Qui a consulté', court: 'Historique', icone: Eye },
            { chemin: '/mon-espace/consentements', libelle: 'Consentements', court: 'Accords', icone: Handshake },
            { chemin: '/mon-espace/preuves', libelle: 'Preuves', icone: Certificate },
          ],
        },
      ]
    case 'agent':
      return [{ liens: liensDemandeur }]
    case 'responsable':
      return [
        { liens: liensDemandeur },
        {
          titre: 'Mon organisme',
          liens: [
            { chemin: '/equipe', libelle: 'Équipe', icone: UsersThree },
            { chemin: '/droits', libelle: 'Droits', icone: Scales },
            { chemin: '/cles', libelle: "Clés d'API", icone: Key },
            { chemin: '/organisme/journal', libelle: 'Journal', icone: ClockCounterClockwise },
          ],
        },
      ]
    case 'gestionnaire':
      return [
        {
          liens: [
            { chemin: '/registre', libelle: 'Mon registre', icone: Database, exact: true },
            { chemin: '/registre/acces', libelle: 'Accès à valider', icone: Scales },
            catalogue,
          ],
        },
      ]
    case 'administrateur':
      return [
        {
          liens: [
            { chemin: '/supervision', libelle: 'Supervision', icone: Pulse },
            { chemin: '/admin/organismes', libelle: 'Organismes', icone: Buildings },
            { chemin: '/admin/utilisateurs', libelle: 'Utilisateurs', icone: Users },
            { chemin: '/admin/droits', libelle: 'Droits', icone: Scales },
            { chemin: '/admin/catalogue', libelle: 'Catalogue', icone: BookOpenText },
            { chemin: '/journal', libelle: 'Journal et intégrité', icone: ClockCounterClockwise },
          ],
        },
      ]
    case 'auditeur':
      return [
        {
          liens: [
            { chemin: '/journal', libelle: 'Journal et intégrité', icone: ClockCounterClockwise },
            catalogue,
          ],
        },
      ]
  }
}

/** Page d'accueil de chaque rôle après la connexion. */
export function accueilDuRole(role: Role): string {
  switch (role) {
    case 'citoyen':
      return '/mon-espace'
    case 'agent':
    case 'responsable':
      return '/tableau-de-bord'
    case 'gestionnaire':
      return '/registre'
    case 'administrateur':
      return '/supervision'
    case 'auditeur':
      return '/journal'
  }
}
