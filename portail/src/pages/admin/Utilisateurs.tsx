import { UserPlus } from '@phosphor-icons/react'
import { useMemo, useState } from 'react'
import { Bouton } from '../../components/Bouton'
import { Select } from '../../components/Champs'
import { Confirmation } from '../../components/Confirmation'
import { Dialogue } from '../../components/Dialogue'
import { Ressource } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { BadgeActif } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Tableau, Td, Th } from '../../components/Tableau'
import { api, messageErreur } from '../../lib/api'
import { useAuth } from '../../lib/auth'
import { LIBELLE_ROLE, libelleRole } from '../../lib/format'
import type { Liste, OrganismeAdmin, Role, UtilisateurAdmin } from '../../lib/types'
import { useApi } from '../../lib/useApi'
import { DialogueUtilisateur, rolesPour } from './DialogueUtilisateur'

type Action = { type: 'revoquer' | 'reactiver'; compte: UtilisateurAdmin }

export function Utilisateurs() {
  const { utilisateur } = useAuth()
  const etat = useApi<Liste<UtilisateurAdmin>>('/admin/utilisateurs')
  const organismes = useApi<Liste<OrganismeAdmin>>('/admin/organisations')
  const [filtre, setFiltre] = useState('')
  const [ajout, setAjout] = useState(false)
  const [action, setAction] = useState<Action | null>(null)
  const [aModifier, setAModifier] = useState<UtilisateurAdmin | null>(null)
  const [nouveauRole, setNouveauRole] = useState<Role | ''>('')
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  const listeOrganismes = useMemo(() => organismes.donnees?.donnees ?? [], [organismes.donnees])
  const visibles = useMemo(
    () => (etat.donnees?.donnees ?? []).filter((u) => !filtre || u.role === filtre),
    [etat.donnees, filtre],
  )

  const rolesDuCompte = aModifier
    ? rolesPour(listeOrganismes.find((o) => o.id === aModifier.organisme_id) ?? null)
    : []
  const roleChoisi = nouveauRole && rolesDuCompte.includes(nouveauRole) ? nouveauRole : (aModifier?.role ?? '')

  async function appliquer() {
    if (!action) return
    setEnvoi(true)
    try {
      await api(`/admin/utilisateurs/${action.compte.id}/${action.type}`, { method: 'POST' })
      setMessage({
        ton: 'succes',
        texte:
          action.type === 'revoquer'
            ? `${action.compte.nom} a perdu l'accès à l'instant.`
            : `${action.compte.nom} peut de nouveau se connecter.`,
      })
      etat.recharger()
    } catch (err) {
      setMessage({ ton: 'erreur', texte: messageErreur(err) })
    } finally {
      setEnvoi(false)
      setAction(null)
    }
  }

  async function changerRole() {
    if (!aModifier || !roleChoisi || roleChoisi === aModifier.role) {
      setAModifier(null)
      return
    }
    setEnvoi(true)
    try {
      await api(`/admin/utilisateurs/${aModifier.id}/role`, { method: 'PATCH', corps: { role: roleChoisi } })
      setMessage({
        ton: 'succes',
        texte: `${aModifier.nom} est maintenant ${libelleRole(roleChoisi).toLowerCase()}. Ses sessions ouvertes ont été fermées.`,
      })
      etat.recharger()
    } catch (err) {
      setMessage({ ton: 'erreur', texte: messageErreur(err) })
    } finally {
      setEnvoi(false)
      setAModifier(null)
    }
  }

  return (
    <>
      <PageHeader
        titre="Utilisateurs"
        description="Tous les comptes de la plateforme. Toute création, changement de rôle ou révocation est immédiat et journalisé."
        actions={
          <Bouton onClick={() => setAjout(true)} icone={<UserPlus size={20} />}>
            Utilisateur
          </Bouton>
        }
      />

      {message && (
        <div className="mb-6">
          <Notice ton={message.ton}>{message.texte}</Notice>
        </div>
      )}

      <div className="mb-5 max-w-sm">
        <Select libelle="Filtrer par rôle" value={filtre} onChange={(e) => setFiltre(e.target.value)}>
          <option value="">Tous les rôles</option>
          {Object.entries(LIBELLE_ROLE).map(([code, libelle]) => (
            <option key={code} value={code}>
              {libelle}
            </option>
          ))}
        </Select>
      </div>

      <Ressource etat={etat} lignes={5}>
        {() => (
          <Tableau legende="Comptes de la plateforme">
            <thead>
              <tr>
                <Th>Compte</Th>
                <Th className="hidden md:table-cell">Rôle</Th>
                <Th className="hidden lg:table-cell">Organisme</Th>
                <Th>État</Th>
                <Th>
                  <span className="sr-only">Actions</span>
                </Th>
              </tr>
            </thead>
            <tbody>
              {visibles.map((u) => {
                const moi = u.id === utilisateur?.id
                return (
                  <tr key={u.id}>
                    <Td>
                      <span className="font-medium text-text">{u.nom}</span>
                      <span className="mt-0.5 block text-muted">{u.email}</span>
                    </Td>
                    <Td className="hidden md:table-cell">{libelleRole(u.role)}</Td>
                    <Td className="mono hidden text-muted lg:table-cell">{u.organisme ?? '-'}</Td>
                    <Td>
                      <BadgeActif actif={u.actif} />
                    </Td>
                    <Td className="space-x-2 whitespace-nowrap text-right">
                      {!moi && u.role !== 'citoyen' && u.actif && (
                        <Bouton
                          variante="discret"
                          taille="s"
                          onClick={() => {
                            setNouveauRole('')
                            setAModifier(u)
                          }}
                        >
                          Rôle
                        </Bouton>
                      )}
                      {!moi &&
                        (u.actif ? (
                          <Bouton variante="secondaire" taille="s" onClick={() => setAction({ type: 'revoquer', compte: u })}>
                            Révoquer
                          </Bouton>
                        ) : (
                          <Bouton variante="secondaire" taille="s" onClick={() => setAction({ type: 'reactiver', compte: u })}>
                            Réactiver
                          </Bouton>
                        ))}
                    </Td>
                  </tr>
                )
              })}
            </tbody>
          </Tableau>
        )}
      </Ressource>

      {ajout && (
        <DialogueUtilisateur
          ouvert
          onFermer={() => setAjout(false)}
          organismes={listeOrganismes}
          onCree={(c) => {
            setAjout(false)
            setMessage({ ton: 'succes', texte: `Compte créé pour ${c.nom} (${libelleRole(c.role)}).` })
            etat.recharger()
          }}
        />
      )}

      <Dialogue
        ouvert={aModifier !== null}
        onFermer={() => setAModifier(null)}
        titre="Changer le rôle"
        description="Seuls les rôles compatibles avec l'organisme du compte sont proposés. Ses sessions ouvertes seront fermées."
        pied={
          <>
            <Bouton variante="secondaire" onClick={() => setAModifier(null)}>
              Annuler
            </Bouton>
            <Bouton onClick={() => void changerRole()} chargement={envoi}>
              Enregistrer
            </Bouton>
          </>
        }
      >
        {aModifier && (
          <div className="space-y-4">
            <p className="text-base text-text">
              {aModifier.nom}
              {aModifier.organisme && <span className="mono text-muted"> · {aModifier.organisme}</span>}
            </p>
            <Select libelle="Nouveau rôle" value={roleChoisi} onChange={(e) => setNouveauRole(e.target.value as Role)}>
              {rolesDuCompte.map((r) => (
                <option key={r} value={r}>
                  {libelleRole(r)}
                </option>
              ))}
            </Select>
          </div>
        )}
      </Dialogue>

      <Confirmation
        ouvert={action !== null}
        titre={action?.type === 'revoquer' ? 'Révoquer ce compte ?' : 'Réactiver ce compte ?'}
        libelleAction={action?.type === 'revoquer' ? 'Révoquer le compte' : 'Réactiver le compte'}
        danger={action?.type === 'revoquer'}
        envoi={envoi}
        onConfirmer={() => void appliquer()}
        onFermer={() => setAction(null)}
      >
        {action && (
          <p>
            {action.type === 'revoquer' ? (
              <>
                {action.compte.nom} ({libelleRole(action.compte.role)}) perdra l'accès{' '}
                <strong className="font-semibold">immédiatement</strong>, sur tous ses appareils.
              </>
            ) : (
              `${action.compte.nom} pourra de nouveau se connecter.`
            )}
          </p>
        )}
      </Confirmation>
    </>
  )
}
