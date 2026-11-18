import type { Reponse, StatutDemande } from './types'

const FUSEAU = 'Africa/Ouagadougou'

const formatDateHeure = new Intl.DateTimeFormat('fr-FR', {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: FUSEAU,
})
const formatDate = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeZone: FUSEAU })
const formatHeure = new Intl.DateTimeFormat('fr-FR', { timeStyle: 'short', timeZone: FUSEAU })

export function dateHeure(iso: string | null | undefined): string {
  if (!iso) return '-'
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '-' : formatDateHeure.format(d)
}

export function dateLongue(iso: string | null | undefined): string {
  if (!iso) return '-'
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '-' : formatDate.format(d)
}

export function heure(iso: string | null | undefined): string {
  if (!iso) return '-'
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '-' : formatHeure.format(d)
}

/** Date au format AAAA-MM-JJ, pour regrouper des lignes par jour. */
export function jour(iso: string): string {
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '' : d.toISOString().slice(0, 10)
}

const relatif = new Intl.RelativeTimeFormat('fr-FR', { numeric: 'auto' })

export function ilYa(iso: string | null | undefined): string {
  if (!iso) return '-'
  const secondes = Math.round((new Date(iso).getTime() - Date.now()) / 1000)
  const abs = Math.abs(secondes)
  if (abs < 60) return relatif.format(secondes, 'second')
  if (abs < 3600) return relatif.format(Math.round(secondes / 60), 'minute')
  if (abs < 86400) return relatif.format(Math.round(secondes / 3600), 'hour')
  return relatif.format(Math.round(secondes / 86400), 'day')
}

/** La date donnée est-elle passée ? (Hors du rendu des composants : l'heure change.) */
export function estPasse(iso: string): boolean {
  return new Date(iso).getTime() < Date.now()
}

/** Aujourd'hui au format AAAA-MM-JJ. */
export function aujourdhui(): string {
  return new Date().toISOString().slice(0, 10)
}

/** Durée restante lisible : « 47 h », « 12 min ». */
export function tempsRestant(iso: string): string {
  const minutes = Math.round((new Date(iso).getTime() - Date.now()) / 60000)
  if (minutes <= 0) return 'expiré'
  if (minutes < 60) return `${minutes} min`
  const heures = Math.round(minutes / 60)
  return heures < 48 ? `${heures} h` : `${Math.round(heures / 24)} jours`
}

export function dureeValidite(minutes: number): string {
  if (minutes < 60) return `${minutes} min`
  if (minutes < 1440) return `${Math.round(minutes / 60)} h`
  return `${Math.round(minutes / 1440)} j`
}

export const LIBELLE_REGISTRE: Record<string, string> = {
  etat_civil: 'État civil',
  nationalite: 'Nationalité',
  casier: 'Casier judiciaire',
}

export function libelleRegistre(registre: string | null | undefined): string {
  if (!registre) return '-'
  return LIBELLE_REGISTRE[registre] ?? registre
}

export const LIBELLE_STATUT: Record<StatutDemande, string> = {
  recue: 'Reçue',
  en_attente_consentement: 'En attente du citoyen',
  en_cours: 'En cours',
  en_attente_registre: 'En attente du registre',
  terminee: 'Terminée',
  ambigue: 'Ambiguë',
  refusee_citoyen: 'Refusée par le citoyen',
  expiree: 'Expirée',
}

export function estEnCours(statut: StatutDemande): boolean {
  return ['recue', 'en_cours', 'en_attente_consentement', 'en_attente_registre'].includes(statut)
}

export function libelleReponse(reponse: Reponse): string {
  if (reponse === 'oui') return 'Oui'
  if (reponse === 'non') return 'Non'
  if (reponse === 'impossible') return 'Impossible'
  return '-'
}

/** Explique en une phrase pourquoi la réponse est « impossible », ou où en est la demande. */
export function explicationMotif(statut: StatutDemande, motif: string | null): string | null {
  if (statut === 'ambigue') {
    return 'Plusieurs personnes correspondent aux critères donnés. Le système refuse de deviner : précisez la recherche.'
  }
  if (statut === 'en_attente_registre') {
    return 'Le registre ne répond pas pour le moment. La demande reprendra automatiquement, sans aucune réponse inventée.'
  }
  if (statut === 'en_attente_consentement') {
    return motif === 'citoyen_sans_compte'
      ? "Le citoyen n'a pas de compte actif : il ne peut pas donner son accord en ligne. Le registre n'est pas interrogé."
      : "Cette question exige l'accord du citoyen. Le registre ne sera interrogé qu'après sa décision."
  }
  if (statut === 'refusee_citoyen') {
    return "Le citoyen a refusé. Le registre n'a pas été interrogé."
  }
  if (statut === 'expiree') {
    return motif === 'registre_indisponible'
      ? 'Le registre est resté indisponible trop longtemps. Aucune réponse n’a été inventée.'
      : "Le citoyen n'a pas répondu à temps."
  }
  switch (motif) {
    case 'introuvable':
      return "Aucune personne ne correspond à ces critères. Ce n'est pas un « non » : la vérification est impossible."
    case 'identite_discordante':
      return "L'identité ne concorde pas : la date de naissance donnée n'est pas celle du registre."
    case 'donnee_indisponible':
      return "Le registre ne fournit pas l'information nécessaire pour répondre."
    default:
      return null
  }
}

export const LIBELLE_ACTION: Record<string, string> = {
  'auth.connexion': 'Connexion',
  'auth.echec': 'Échec de connexion',
  'demande.creee': 'Demande posée',
  'demande.refusee': 'Demande refusée',
  'consentement.demande': 'Consentement demandé',
  'consentement.accepte': 'Consentement accordé',
  'consentement.refuse': 'Consentement refusé',
  'consentement.retire': 'Consentement retiré',
  'consentement.expire': 'Consentement expiré',
  'donnee.consultee': 'Registre consulté',
  'registre.indisponible': 'Registre indisponible',
  'attestation.emise': 'Attestation émise',
  'compte.cree': 'Compte créé',
  'compte.revoque': 'Compte révoqué',
  'organisme.cree': 'Organisme créé',
  'organisme.active': 'Organisme activé',
  'organisme.suspendu': 'Organisme suspendu',
  'droit.demande': 'Droit demandé',
  'droit.accorde': 'Droit accordé',
  'droit.valide': 'Droit validé',
  'droit.refuse': 'Droit refusé',
  'droit.retire': 'Droit retiré',
  'config.modifiee': 'Configuration modifiée',
  'cle.creee': "Clé d'API créée",
  'cle.revoquee': "Clé d'API révoquée",
}

export function libelleAction(action: string): string {
  return LIBELLE_ACTION[action] ?? action
}

const LIBELLE_RESULTAT: Record<string, string> = {
  succes: 'Réussi',
  echec: 'Échec',
  accepte: 'Accepté',
  refuse: 'Refusé',
  retire: 'Retiré',
  expire: 'Expiré',
  indisponible: 'Indisponible',
  emise: 'Émise',
  oui: 'Réponse : oui',
  non: 'Réponse : non',
  impossible: 'Réponse : impossible',
  en_attente: 'En attente',
  en_attente_validation: 'En attente de validation',
  creee: 'Créée',
  cree: 'Créé',
  revoquee: 'Révoquée',
  revoque: 'Révoqué',
  valide: 'Validé',
  modifie: 'Modifié',
  preparation: 'En préparation',
  actif: 'Actif',
  suspendu: 'Suspendu',
}

export function libelleResultat(resultat: string | null): string {
  if (!resultat) return '-'
  return LIBELLE_RESULTAT[resultat] ?? resultat.replace(/_/g, ' ')
}

export const LIBELLE_ROLE: Record<string, string> = {
  citoyen: 'Citoyen',
  agent: 'Agent',
  responsable: "Responsable d'organisme",
  gestionnaire: 'Gestionnaire de registre',
  administrateur: 'Administrateur',
  auditeur: 'Auditeur',
}

export function libelleRole(role: string): string {
  return LIBELLE_ROLE[role] ?? role
}

/** Lien public de vérification d'une attestation. */
export function lienVerification(jeton: string): string {
  return `${window.location.origin}/verifier?jeton=${encodeURIComponent(jeton)}`
}

/**
 * Lit la date de fin de validité inscrite dans une attestation, pour l'afficher.
 * Ne vérifie aucune signature : seule la page de vérification fait foi.
 */
export function validiteDeLAttestation(jeton: string): string | undefined {
  try {
    const charge = jeton.split('.')[0]
    const base64 = charge.replace(/-/g, '+').replace(/_/g, '/')
    const json = new TextDecoder().decode(
      Uint8Array.from(atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '=')), (c) => c.charCodeAt(0)),
    )
    const valeur = (JSON.parse(json) as { valide_jusqu_au?: unknown }).valide_jusqu_au
    return typeof valeur === 'string' ? valeur : undefined
  } catch {
    return undefined
  }
}
