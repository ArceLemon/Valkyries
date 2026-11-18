import { ArrowLeft, CheckCircle, Circle, Plus, Scales, UserPlus } from '@phosphor-icons/react'
import { useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Bouton } from '../../components/Bouton'
import { Input, Select } from '../../components/Champs'
import { Confirmation } from '../../components/Confirmation'
import { Dialogue } from '../../components/Dialogue'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Panneau } from '../../components/Panneau'
import { BadgeActif, Pastille } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Tableau, Td, Th } from '../../components/Tableau'
import { ApiError, api, messageErreur } from '../../lib/api'
import { libelleRegistre, libelleRole } from '../../lib/format'
import type { DroitOrganisme, Liste, OrganismeDetail as Detail, QuestionAdmin } from '../../lib/types'
import { useApi } from '../../lib/useApi'
import { DialogueUtilisateur } from './DialogueUtilisateur'
import { BadgeStatutOrganisme } from './Organismes'

function BadgeDroit({ etat }: { etat: DroitOrganisme['etat'] }) {
  if (etat === 'valide') return <Pastille ton="ok">Validé par la source</Pastille>
  if (etat === 'expire') return <Pastille ton="refus">Expiré</Pastille>
  return <Pastille ton="attente">En attente de la source</Pastille>
}

function Etape({ faite, children }: { faite: boolean; children: ReactNode }) {
  return (
    <li className="flex gap-3">
      {faite ? (
        <CheckCircle size={22} weight="fill" className="shrink-0 text-accent" aria-label="Fait" />
      ) : (
        <Circle size={22} className="shrink-0 text-muted" aria-label="À faire" />
      )}
      <span className={faite ? 'text-muted line-through' : 'text-text'}>{children}</span>
    </li>
  )
}

export function OrganismeDetail() {
  const { id } = useParams()
  const etat = useApi<Detail>(`/admin/organisations/${id}`)
  const questions = useApi<Liste<QuestionAdmin>>('/admin/catalogue')
  const [ajoutCompte, setAjoutCompte] = useState(false)
  const [ajoutDroit, setAjoutDroit] = useState(false)
  const [question, setQuestion] = useState('')
  const [finalite, setFinalite] = useState('')
  const [erreursDroit, setErreursDroit] = useState<Record<string, string>>({})
  const [changement, setChangement] = useState<'activer' | 'suspendre' | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  async function creerDroit(e: FormEvent, o: Detail) {
    e.preventDefault()
    const choisie = question || questions.donnees?.donnees[0]?.code || ''
    if (!finalite.trim()) {
      setErreursDroit({ finalite: 'Indiquez pourquoi cet organisme a besoin de cette information.' })
      return
    }
    setEnvoi(true)
    try {
      await api('/admin/droits', { method: 'POST', corps: { organisme: o.sigle, question: choisie, finalite: finalite.trim() } })
      setMessage({ ton: 'succes', texte: "Droit enregistré. Il n'aura d'effet qu'après validation par le gestionnaire du registre." })
      setAjoutDroit(false)
      setFinalite('')
      setErreursDroit({})
      etat.recharger()
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.erreurs).length > 0) {
        setErreursDroit(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])))
      } else {
        setErreursDroit({ finalite: messageErreur(err) })
      }
    } finally {
      setEnvoi(false)
    }
  }

  async function changerStatut(o: Detail) {
    if (!changement) return
    setEnvoi(true)
    try {
      await api(`/admin/organisations/${o.id}/${changement}`, { method: 'POST' })
      setMessage({
        ton: 'succes',
        texte: changement === 'activer' ? `« ${o.nom} » est actif : ses comptes peuvent se connecter.` : `« ${o.nom} » est suspendu.`,
      })
      etat.recharger()
    } catch (err) {
      setMessage({ ton: 'erreur', texte: messageErreur(err) })
    } finally {
      setEnvoi(false)
      setChangement(null)
    }
  }

  return (
    <Ressource etat={etat} lignes={6}>
      {(o) => {
        const demandeur = o.roles.includes('demandeuse')
        const fournisseur = o.roles.includes('fournisseuse')
        const comptesActifs = o.utilisateurs.filter((u) => u.actif)
        const aResponsable = comptesActifs.some((u) => u.role === 'responsable')
        const aGestionnaire = comptesActifs.some((u) => u.role === 'gestionnaire')

        return (
          <>
            <Link to="/admin/organismes" className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline">
              <ArrowLeft size={16} /> Tous les organismes
            </Link>
            <PageHeader
              titre={o.nom}
              description={
                <span className="flex flex-wrap items-center gap-2">
                  <span className="mono">{o.sigle}</span>
                  <BadgeStatutOrganisme statut={o.statut} />
                  {o.tutelle && <span className="text-muted">· {o.tutelle}</span>}
                </span>
              }
              actions={
                o.statut === 'actif' ? (
                  <Bouton variante="secondaire" onClick={() => setChangement('suspendre')}>
                    Suspendre
                  </Bouton>
                ) : (
                  <Bouton onClick={() => setChangement('activer')}>Activer l'organisme</Bouton>
                )
              }
            />

            {message && (
              <div className="mb-6">
                <Notice ton={message.ton}>{message.texte}</Notice>
              </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
              <div className="space-y-6">
                <Panneau
                  titre="Comptes"
                  description="Les personnes qui travaillent pour cet organisme sur la plateforme."
                  actions={
                    <Bouton taille="s" icone={<UserPlus size={18} />} onClick={() => setAjoutCompte(true)}>
                      Utilisateur
                    </Bouton>
                  }
                >
                  {o.utilisateurs.length === 0 ? (
                    <Vide
                      titre="Aucun compte"
                      texte={
                        demandeur
                          ? "Commencez par créer le responsable : il pourra ensuite ajouter lui-même ses agents."
                          : 'Créez le gestionnaire du registre : il validera les accès demandés.'
                      }
                    />
                  ) : (
                    <Tableau legende={`Comptes de ${o.nom}`}>
                      <thead>
                        <tr>
                          <Th>Compte</Th>
                          <Th>Rôle</Th>
                          <Th>État</Th>
                        </tr>
                      </thead>
                      <tbody>
                        {o.utilisateurs.map((u) => (
                          <tr key={u.id}>
                            <Td>
                              <span className="font-medium text-text">{u.nom}</span>
                              <span className="mt-0.5 block text-muted">{u.email}</span>
                            </Td>
                            <Td>{libelleRole(u.role)}</Td>
                            <Td>
                              <BadgeActif actif={u.actif} />
                            </Td>
                          </tr>
                        ))}
                      </tbody>
                    </Tableau>
                  )}
                </Panneau>

                {demandeur && (
                  <Panneau
                    titre="Droits"
                    description="Les questions que cet organisme peut poser, et pour quelle finalité."
                    actions={
                      <Bouton taille="s" variante="secondaire" icone={<Plus size={18} />} onClick={() => setAjoutDroit(true)}>
                        Droit
                      </Bouton>
                    }
                  >
                    {o.droits.length === 0 ? (
                      <Vide icone={Scales} titre="Aucun droit" texte="Sans droit validé par la source, l'organisme ne peut poser aucune question." />
                    ) : (
                      <ul className="divide-y divide-border">
                        {o.droits.map((d) => (
                          <li key={d.id} className="flex flex-wrap items-start justify-between gap-2 py-3">
                            <span className="min-w-0">
                              <span className="block font-medium text-text">{d.libelle}</span>
                              <span className="block text-sm text-muted">
                                {d.finalite} · {libelleRegistre(d.registre)}
                              </span>
                            </span>
                            <BadgeDroit etat={d.etat} />
                          </li>
                        ))}
                      </ul>
                    )}
                  </Panneau>
                )}

                {fournisseur && (
                  <Panneau titre="Registres détenus">
                    <p className="text-base text-text">
                      {o.registres && o.registres.length > 0 ? o.registres.map(libelleRegistre).join(', ') : 'Aucun registre déclaré.'}
                    </p>
                  </Panneau>
                )}
              </div>

              <aside aria-label="Mise en service">
                <Panneau titre="Mise en service" className="lg:sticky lg:top-6">
                  <ol className="space-y-4 text-base">
                    <Etape faite>Organisme créé</Etape>
                    {demandeur && <Etape faite={aResponsable}>Créer le responsable de l'organisme</Etape>}
                    {fournisseur && <Etape faite={aGestionnaire}>Créer le gestionnaire du registre</Etape>}
                    {demandeur && (
                      <Etape faite={o.droits.length > 0}>Accorder au moins un droit (la source devra le valider)</Etape>
                    )}
                    <Etape faite={o.statut === 'actif'}>Activer l'organisme</Etape>
                  </ol>
                  {demandeur && (
                    <p className="mt-4 border-t border-border pt-4 text-sm text-muted">
                      Une fois actif, le responsable se connecte, ajoute ses agents et crée ses clés d'API depuis son propre espace.
                    </p>
                  )}
                </Panneau>
              </aside>
            </div>

            {ajoutCompte && (
              <DialogueUtilisateur
                ouvert
                onFermer={() => setAjoutCompte(false)}
                organismes={[]}
                organismeFixe={{ ...o, utilisateurs: o.utilisateurs.length }}
                onCree={(c) => {
                  setAjoutCompte(false)
                  setMessage({ ton: 'succes', texte: `Compte créé pour ${c.nom} (${libelleRole(c.role)}).` })
                  etat.recharger()
                }}
              />
            )}

            <Dialogue
              ouvert={ajoutDroit}
              onFermer={() => setAjoutDroit(false)}
              titre="Accorder un droit"
              description="Le droit reste sans effet tant que le gestionnaire du registre concerné ne l'a pas validé."
              pied={
                <>
                  <Bouton variante="secondaire" onClick={() => setAjoutDroit(false)}>
                    Annuler
                  </Bouton>
                  <Bouton type="submit" form="formulaire-droit" chargement={envoi}>
                    Enregistrer le droit
                  </Bouton>
                </>
              }
            >
              <form id="formulaire-droit" onSubmit={(e) => void creerDroit(e, o)} noValidate className="space-y-5">
                <Select libelle="Question" value={question || questions.donnees?.donnees[0]?.code || ''} onChange={(e) => setQuestion(e.target.value)}>
                  {(questions.donnees?.donnees ?? []).map((q) => (
                    <option key={q.code} value={q.code}>
                      {q.libelle} ({libelleRegistre(q.registre)})
                    </option>
                  ))}
                </Select>
                <Input
                  libelle="Finalité"
                  aide="Par exemple : Immatriculation d'une entreprise."
                  value={finalite}
                  onChange={(e) => setFinalite(e.target.value)}
                  erreur={erreursDroit.finalite}
                  required
                />
              </form>
            </Dialogue>

            <Confirmation
              ouvert={changement !== null}
              titre={changement === 'suspendre' ? 'Suspendre cet organisme ?' : 'Activer cet organisme ?'}
              libelleAction={changement === 'suspendre' ? "Suspendre l'organisme" : "Activer l'organisme"}
              danger={changement === 'suspendre'}
              envoi={envoi}
              onConfirmer={() => void changerStatut(o)}
              onFermer={() => setChangement(null)}
            >
              <p>
                {changement === 'suspendre'
                  ? `Tous les comptes et toutes les clés d'API de « ${o.nom} » perdent l'accès immédiatement.`
                  : `Les comptes de « ${o.nom} » pourront se connecter et utiliser leurs droits validés.`}
              </p>
            </Confirmation>
          </>
        )
      }}
    </Ressource>
  )
}
