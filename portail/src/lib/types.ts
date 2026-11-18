// Types partagés avec l'API KUMA BANA.

export type Role =
  | 'citoyen'
  | 'agent'
  | 'responsable'
  | 'gestionnaire'
  | 'administrateur'
  | 'auditeur'

export interface Organisme {
  id: number
  nom: string
  sigle: string
  registres: string[] | null
}

export interface Utilisateur {
  id: number
  nom: string
  email: string | null
  identifiant?: string | null
  role: Role
  organisme: Organisme | null
}

export interface Liste<T> {
  donnees: T[]
}

export type StatutDemande =
  | 'recue'
  | 'en_attente_consentement'
  | 'en_cours'
  | 'en_attente_registre'
  | 'terminee'
  | 'ambigue'
  | 'refusee_citoyen'
  | 'expiree'

export type Reponse = 'oui' | 'non' | 'impossible' | null

export interface Demande {
  reference: string
  lot: string | null
  question: string
  libelle: string
  parametre: string | null
  finalite: string
  statut: StatutDemande
  reponse: Reponse
  motif: string | null
  source: string | null
  consentement: string | null
  /** Un code SMS est attendu : le citoyen peut le lire à l'agent au lieu d'ouvrir l'application. */
  code_attendu?: boolean
  attestation: string | null
  creee_le: string | null
  traitee_le: string | null
}

export interface DroitDisponible {
  question: string
  libelle: string
  description: string | null
  finalite: string
  regime: 'base_legale' | 'consentement'
  sensibilite: 'normale' | 'elevee'
  parametree: boolean
}

export interface Demarche {
  code: string
  libelle: string
  finalite: string
  questions: { question: string; libelle: string; parametre: string | null }[]
}

/** Une question d'une démarche lancée : posée (avec son état) ou rejetée (droit absent, quota...). */
export interface ElementDemarche {
  question: string
  libelle?: string
  statut: StatutDemande | 'rejetee'
  reponse?: Reponse
  reference?: string
  erreur?: string
}

export interface ResultatDemarche {
  lot: string
  demarche: string
  demandes: ElementDemarche[]
  pieces_evitees: number
}

export type EtatRegistre = 'en_service' | 'lent' | 'panne'

export interface Registre {
  registre: string
  etat: EtatRegistre
  latence_ms: number | null
  verifie_le: string | null
  disjoncteur_ouvert: boolean
}

export interface QuestionCatalogue {
  code: string
  libelle: string
  description: string | null
  source: string | null
  registre: string
  sensibilite: 'normale' | 'elevee'
  regime: 'base_legale' | 'consentement'
  parametree: boolean
  validite_minutes: number
  reponses: string[]
  base_juridique: string | null
}

export interface QuestionAdmin {
  id: number
  code: string
  libelle: string
  registre: string
  regle: string
  sensibilite: 'normale' | 'elevee'
  regime: 'base_legale' | 'consentement'
  validite_minutes: number
  parametree: boolean
  publie: boolean
}

export interface LigneJournal {
  id: number
  date: string
  acteur_type: string
  acteur_id: string | null
  organisme: number | null
  action: string
  objet: string | null
  sujet: string | null
  details: Record<string, unknown>
  resultat: string | null
  hash: string
}

export interface VerificationJournal {
  valide: boolean
  total: number
  rupture: { id: number; motif: string } | null
  points_de_controle?: number
}

export interface PointDeControle {
  id: number
  dernier_id: number
  total: number
  hash: string
  kid: string
  jeton: string
  cree_le: string | null
}

export interface MembreEquipe {
  id: number
  nom: string
  email: string
  role: Role
  actif: boolean
}

export interface DroitOrganisme {
  id: number
  question: string
  libelle: string
  finalite: string
  etat: 'en_attente' | 'valide' | 'expire'
  justification: string | null
  reference_juridique: string | null
  expire_le: string | null
}

export interface AccesRegistre {
  id: number
  organisme: string
  question: string
  registre: string
  finalite: string
  justification: string | null
  etat: 'en_attente' | 'valide' | 'expire'
  reference_juridique: string | null
  expire_le: string | null
}

export interface CleApi {
  id: number
  nom: string
  identifiant: string
  questions: string[] | null
  quota_par_minute: number | null
  signature_obligatoire: boolean
  expire_le: string | null
  revoquee: boolean
  dernier_usage_le: string | null
}

export interface OrganismeAdmin {
  id: number
  nom: string
  sigle: string
  tutelle: string | null
  roles: string[]
  registres: string[] | null
  statut: 'preparation' | 'actif' | 'suspendu'
  utilisateurs: number
}

export interface UtilisateurAdmin {
  id: number
  nom: string
  email: string
  role: Role
  organisme: string | null
  organisme_id: number | null
  actif: boolean
}

export interface DroitOrganisme {
  id: number
  question: string
  libelle: string
  registre: string
  finalite: string
  etat: 'en_attente' | 'valide' | 'expire'
  expire_le: string | null
}

export interface OrganismeDetail extends Omit<OrganismeAdmin, 'utilisateurs'> {
  utilisateurs: UtilisateurAdmin[]
  droits: DroitOrganisme[]
  /** Rôles que peuvent avoir les comptes de cet organisme. */
  roles_comptes: Role[]
}

export interface ConsentementACiter {
  id: number
  organisme: string
  question: string
  finalite: string
  sensibilite: 'normale' | 'elevee'
  expire_le: string
}

export interface ConsentementCitoyen {
  id: number
  statut: 'accepte' | 'retire' | 'refuse' | 'expire'
  organisme: string
  question: string
  finalite: string
  decide_le: string | null
}

export interface ConsultationCitoyen {
  date: string
  type: string
  organisme: string | null
  question: string | null
  finalite: string | null
  regime: string | null
  reponse: string | null
}

export interface PreuveCitoyen {
  reference: string
  question: string
  organisme: string
  valide_jusqu_au: string
  revoquee: boolean
  jeton: string
}

export interface ResultatVerification {
  etat: 'valide' | 'expiree' | 'alteree' | 'revoquee' | 'cle_inconnue'
  attestation: {
    question: string
    parametre: string | null
    reponse: string
    demandeur: string
    source: string
    emise_le: string
    valide_jusqu_au: string
    kid: string
  } | null
}

export interface Statistiques {
  total: number
  aujourdhui: number
  semaine: number
  semaine_precedente: number
  papiers_evites: number
  taux_reponse: number | null
  delai_median_secondes: number | null
  attente_citoyen: number
  attente_registre: number
  repartition: {
    oui: number
    non: number
    impossible: number
    refusee: number
    expiree: number
    en_cours: number
  }
  serie: { date: string; total: number; obtenues: number }[]
  top_questions: { libelle: string; demandes: number }[]
}

export interface StatistiquesPlateforme extends Statistiques {
  par_registre: { registre: string; demandes: number; impossibles: number }[]
  organismes_actifs: number
  organismes_total: number
  comptes_actifs: number
  citoyens: number
  consentements: { en_attente: number; accepte: number; refuse: number; retire: number }
  attestations: { emises: number; valides: number; revoquees: number }
  journal: { lignes: number; points_de_controle: number }
}
