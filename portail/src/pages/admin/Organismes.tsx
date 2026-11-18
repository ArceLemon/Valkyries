import { Buildings, CheckCircle, Clock, XCircle } from '@phosphor-icons/react'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Bouton, classesBouton } from '../../components/Bouton'
import { Case, Input } from '../../components/Champs'
import { Confirmation } from '../../components/Confirmation'
import { Dialogue } from '../../components/Dialogue'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Pastille } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Tableau, Td, Th } from '../../components/Tableau'
import { ApiError, api, messageErreur } from '../../lib/api'
import { LIBELLE_REGISTRE, libelleRegistre } from '../../lib/format'
import type { Liste, OrganismeAdmin } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function BadgeStatutOrganisme({ statut }: { statut: OrganismeAdmin['statut'] }) {
  if (statut === 'actif') return <Pastille ton="ok" icone={CheckCircle}>Actif</Pastille>
  if (statut === 'suspendu') return <Pastille ton="refus" icone={XCircle}>Suspendu</Pastille>
  return <Pastille ton="attente" icone={Clock}>En préparation</Pastille>
}

type Action = { type: 'activer' | 'suspendre'; organisme: OrganismeAdmin }

export function Organismes() {
  const navigate = useNavigate()
  const etat = useApi<Liste<OrganismeAdmin>>('/admin/organisations')
  const [ouvert, setOuvert] = useState(false)
  const [nom, setNom] = useState('')
  const [sigle, setSigle] = useState('')
  const [tutelle, setTutelle] = useState('')
  const [roles, setRoles] = useState<string[]>(['demandeuse'])
  const [registres, setRegistres] = useState<string[]>([])
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const [action, setAction] = useState<Action | null>(null)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  function fermer() {
    setOuvert(false)
    setErreurs({})
  }

  function bascule(liste: string[], valeur: string, actif: boolean): string[] {
    return actif ? [...liste, valeur] : liste.filter((v) => v !== valeur)
  }

  async function creer(e: FormEvent) {
    e.preventDefault()
    const trouvees: Record<string, string> = {}
    if (!nom.trim()) trouvees.nom = "Indiquez le nom de l'organisme."
    if (!sigle.trim()) trouvees.sigle = 'Indiquez un sigle court et unique.'
    if (roles.length === 0) trouvees.roles = 'Choisissez au moins un rôle.'
    setErreurs(trouvees)
    if (Object.keys(trouvees).length > 0) return

    setEnvoi(true)
    try {
      const cree = await api<{ id: number }>('/admin/organisations', {
        method: 'POST',
        corps: {
          nom: nom.trim(),
          sigle: sigle.trim().toUpperCase(),
          tutelle: tutelle.trim() || undefined,
          roles,
          registres: roles.includes('fournisseuse') && registres.length > 0 ? registres : undefined,
        },
      })
      fermer()
      // Suite logique : la fiche de l'organisme, pour créer ses comptes, ses droits, puis l'activer.
      navigate(`/admin/organismes/${cree.id}`)
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

  async function appliquer() {
    if (!action) return
    setEnvoi(true)
    try {
      await api(`/admin/organisations/${action.organisme.id}/${action.type}`, { method: 'POST' })
      setMessage({
        ton: 'succes',
        texte:
          action.type === 'suspendre'
            ? `« ${action.organisme.nom} » est suspendu : ses agents et ses clés d'API sont coupés à l'instant.`
            : `« ${action.organisme.nom} » est actif.`,
      })
      etat.recharger()
    } catch (err) {
      setMessage({ ton: 'erreur', texte: messageErreur(err) })
    } finally {
      setEnvoi(false)
      setAction(null)
    }
  }

  return (
    <>
      <PageHeader
        titre="Organismes"
        description="Les administrations qui demandent ou détiennent des données. Une suspension coupe tous leurs accès immédiatement."
        actions={
          <Bouton onClick={() => setOuvert(true)} icone={<Buildings size={20} />}>
            Créer un organisme
          </Bouton>
        }
      />

      {message && (
        <div className="mb-6">
          <Notice ton={message.ton}>{message.texte}</Notice>
        </div>
      )}

      <Ressource etat={etat} lignes={4}>
        {(r) =>
          r.donnees.length === 0 ? (
            <Vide titre="Aucun organisme" texte="Créez la première administration." />
          ) : (
            <Tableau legende="Organismes de la plateforme">
              <thead>
                <tr>
                  <Th>Organisme</Th>
                  <Th>État</Th>
                  <Th className="hidden md:table-cell">Rôles</Th>
                  <Th className="hidden lg:table-cell">Comptes</Th>
                  <Th>
                    <span className="sr-only">Actions</span>
                  </Th>
                </tr>
              </thead>
              <tbody>
                {r.donnees.map((o) => (
                  <tr key={o.id}>
                    <Td>
                      <Link to={`/admin/organismes/${o.id}`} className="font-medium text-accent hover:underline">
                        {o.nom}
                      </Link>
                      <span className="mono mt-0.5 block text-muted">{o.sigle}</span>
                    </Td>
                    <Td>
                      <BadgeStatutOrganisme statut={o.statut} />
                    </Td>
                    <Td className="hidden text-muted md:table-cell">
                      {o.roles.map((x) => (x === 'demandeuse' ? 'Demandeuse' : 'Fournisseuse')).join(', ')}
                      {o.registres && o.registres.length > 0 && (
                        <span className="block">{o.registres.map(libelleRegistre).join(', ')}</span>
                      )}
                    </Td>
                    <Td className="hidden text-muted lg:table-cell">{o.utilisateurs}</Td>
                    <Td className="space-x-2 whitespace-nowrap text-right">
                      <Link to={`/admin/organismes/${o.id}`} className={classesBouton('primaire', 's')}>
                        Gérer
                      </Link>
                      {o.statut === 'actif' ? (
                        <Bouton variante="secondaire" taille="s" onClick={() => setAction({ type: 'suspendre', organisme: o })}>
                          Suspendre
                        </Bouton>
                      ) : (
                        <Bouton variante="secondaire" taille="s" onClick={() => setAction({ type: 'activer', organisme: o })}>
                          Activer
                        </Bouton>
                      )}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Tableau>
          )
        }
      </Ressource>

      <Dialogue
        ouvert={ouvert}
        onFermer={fermer}
        titre="Créer un organisme"
        description="Il sera créé en préparation : aucun accès tant qu'il n'est pas activé."
        pied={
          <>
            <Bouton variante="secondaire" onClick={fermer}>
              Annuler
            </Bouton>
            <Bouton type="submit" form="formulaire-organisme" chargement={envoi}>
              Créer l'organisme
            </Bouton>
          </>
        }
      >
        <form id="formulaire-organisme" onSubmit={creer} noValidate className="space-y-5">
          <Input libelle="Nom" value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} required />
          <Input libelle="Sigle" aide="Court et unique, par exemple RCCM." value={sigle} onChange={(e) => setSigle(e.target.value)} erreur={erreurs.sigle} required />
          <Input libelle="Ministère de tutelle" value={tutelle} onChange={(e) => setTutelle(e.target.value)} />

          <fieldset>
            <legend className="text-sm font-medium text-text">Rôles</legend>
            {erreurs.roles && <p role="alert" className="text-sm font-medium text-danger-soft-text">{erreurs.roles}</p>}
            <Case libelle="Demandeuse" aide="Pose des questions pour traiter des dossiers." checked={roles.includes('demandeuse')} onChange={(e) => setRoles((r) => bascule(r, 'demandeuse', e.target.checked))} />
            <Case libelle="Fournisseuse" aide="Détient un registre et répond aux questions." checked={roles.includes('fournisseuse')} onChange={(e) => setRoles((r) => bascule(r, 'fournisseuse', e.target.checked))} />
          </fieldset>

          {roles.includes('fournisseuse') && (
            <fieldset>
              <legend className="text-sm font-medium text-text">Registres détenus</legend>
              {Object.entries(LIBELLE_REGISTRE).map(([code, libelle]) => (
                <Case key={code} libelle={libelle} checked={registres.includes(code)} onChange={(e) => setRegistres((r) => bascule(r, code, e.target.checked))} />
              ))}
            </fieldset>
          )}
        </form>
      </Dialogue>

      <Confirmation
        ouvert={action !== null}
        titre={action?.type === 'suspendre' ? 'Suspendre cet organisme ?' : 'Activer cet organisme ?'}
        libelleAction={action?.type === 'suspendre' ? "Suspendre l'organisme" : "Activer l'organisme"}
        danger={action?.type === 'suspendre'}
        envoi={envoi}
        onConfirmer={() => void appliquer()}
        onFermer={() => setAction(null)}
      >
        {action && (
          <p>
            {action.type === 'suspendre'
              ? `Tous les agents de « ${action.organisme.nom} » et toutes ses clés d'API perdent l'accès immédiatement. L'historique est conservé.`
              : `« ${action.organisme.nom} » pourra se connecter et utiliser ses droits.`}
          </p>
        )}
      </Confirmation>
    </>
  )
}
