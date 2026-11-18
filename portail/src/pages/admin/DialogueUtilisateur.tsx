import { useState } from 'react'
import type { FormEvent } from 'react'
import { Bouton } from '../../components/Bouton'
import { Input, Select } from '../../components/Champs'
import { Dialogue } from '../../components/Dialogue'
import { ApiError, api, messageErreur } from '../../lib/api'
import { libelleRole } from '../../lib/format'
import type { OrganismeAdmin, Role, UtilisateurAdmin } from '../../lib/types'

/** Rôles possibles selon l'organisme (même règle que le serveur). */
export function rolesPour(organisme: Pick<OrganismeAdmin, 'roles'> | null): Role[] {
  if (!organisme) return ['administrateur', 'auditeur']
  const roles: Role[] = []
  if (organisme.roles.includes('demandeuse')) roles.push('responsable', 'agent')
  if (organisme.roles.includes('fournisseuse')) roles.push('gestionnaire')
  return roles
}

const AIDE_ROLE: Partial<Record<Role, string>> = {
  responsable: "Gère l'équipe, les droits et les clés d'API de l'organisme, et pose des questions.",
  agent: 'Pose des questions sur les citoyens pour traiter les dossiers.',
  gestionnaire: 'Valide ou refuse les accès demandés à son registre.',
  administrateur: 'Administre toute la plateforme. Ne voit aucune donnée de citoyen.',
  auditeur: "Consulte le journal et vérifie son intégrité, en lecture seule.",
}

/**
 * Création d'un compte avec son rôle. Si `organismeFixe` est donné (fiche d'un
 * organisme), l'organisme n'est pas modifiable ; sinon l'administrateur le choisit.
 * À monter seulement quand il est ouvert : le formulaire repart ainsi toujours vide.
 */
export function DialogueUtilisateur({
  ouvert,
  onFermer,
  onCree,
  organismes,
  organismeFixe,
}: {
  ouvert: boolean
  onFermer: () => void
  onCree: (compte: UtilisateurAdmin) => void
  organismes: OrganismeAdmin[]
  organismeFixe?: OrganismeAdmin | null
}) {
  const [nom, setNom] = useState('')
  const [email, setEmail] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [organismeId, setOrganismeId] = useState<string>('')
  const [role, setRole] = useState<Role | ''>('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)

  const organisme = organismeFixe ?? organismes.find((o) => String(o.id) === organismeId) ?? null
  const rolesPossibles = rolesPour(organisme)
  const roleActif: Role | '' = role && rolesPossibles.includes(role) ? role : (rolesPossibles[0] ?? '')

  async function creer(e: FormEvent) {
    e.preventDefault()
    const trouvees: Record<string, string> = {}
    if (!nom.trim()) trouvees.nom = 'Indiquez le nom de la personne.'
    if (!email.trim()) trouvees.email = "Indiquez l'adresse e-mail."
    if (motDePasse.length < 12) trouvees.mot_de_passe = 'Au moins 12 caractères.'
    if (!roleActif) trouvees.role = "Cet organisme n'accepte aucun compte : vérifiez ses rôles."
    setErreurs(trouvees)
    if (Object.keys(trouvees).length > 0) return

    setEnvoi(true)
    try {
      const compte = await api<UtilisateurAdmin>('/admin/utilisateurs', {
        method: 'POST',
        corps: {
          nom: nom.trim(),
          email: email.trim(),
          mot_de_passe: motDePasse,
          role: roleActif,
          organisme_id: organisme?.id ?? null,
        },
      })
      onCree(compte)
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.erreurs).length > 0) {
        setErreurs(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])))
      } else {
        setErreurs({ nom: messageErreur(err) })
      }
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Dialogue
      ouvert={ouvert}
      onFermer={onFermer}
      titre="Ajouter un utilisateur"
      description="Le compte est actif tout de suite. Transmettez le mot de passe à la personne par un canal sûr."
      pied={
        <>
          <Bouton variante="secondaire" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="formulaire-utilisateur" chargement={envoi}>
            Créer le compte
          </Bouton>
        </>
      }
    >
      <form id="formulaire-utilisateur" onSubmit={creer} noValidate className="space-y-5">
        <Input libelle="Nom complet" value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} required />
        <Input
          libelle="Adresse e-mail"
          type="email"
          autoComplete="off"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          erreur={erreurs.email}
          required
        />
        <Input
          libelle="Mot de passe provisoire"
          type="text"
          autoComplete="new-password"
          aide="Au moins 12 caractères."
          value={motDePasse}
          onChange={(e) => setMotDePasse(e.target.value)}
          erreur={erreurs.mot_de_passe}
          required
        />

        {organismeFixe ? (
          <p className="text-sm text-muted">
            Organisme : <strong className="font-semibold text-text">{organismeFixe.nom}</strong>
          </p>
        ) : (
          <Select
            libelle="Organisme"
            aide="Aucun organisme pour un administrateur ou un auditeur de la plateforme."
            value={organismeId}
            onChange={(e) => setOrganismeId(e.target.value)}
            erreur={erreurs.organisme_id}
          >
            <option value="">Aucun (plateforme)</option>
            {organismes.map((o) => (
              <option key={o.id} value={o.id}>
                {o.nom} ({o.sigle})
              </option>
            ))}
          </Select>
        )}

        <Select
          libelle="Rôle"
          aide={roleActif ? AIDE_ROLE[roleActif] : undefined}
          value={roleActif}
          onChange={(e) => setRole(e.target.value as Role)}
          erreur={erreurs.role}
        >
          {rolesPossibles.map((r) => (
            <option key={r} value={r}>
              {libelleRole(r)}
            </option>
          ))}
        </Select>
      </form>
    </Dialogue>
  )
}
