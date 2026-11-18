import { useState } from 'react'
import type { FormEvent } from 'react'
import { Bouton } from '../../components/Bouton'
import { Input } from '../../components/Champs'
import { Confirmation } from '../../components/Confirmation'
import { Dialogue } from '../../components/Dialogue'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { BadgeEtatDroit } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Segmente } from '../../components/Segmente'
import { ApiError, api, messageErreur } from '../../lib/api'
import { dateLongue } from '../../lib/format'
import type { AccesRegistre, Liste } from '../../lib/types'
import { useApi } from '../../lib/useApi'
import { useValidation } from '../../lib/validationContexte'

type Onglet = 'en_attente' | 'valide'

type Action = { type: 'valider' | 'refuser' | 'retirer'; acces: AccesRegistre }

export function Acces() {
  const confirmer = useValidation()
  const [onglet, setOnglet] = useState<Onglet>('en_attente')
  const etat = useApi<Liste<AccesRegistre>>(`/registre/acces?statut=${onglet}`)
  const [action, setAction] = useState<Action | null>(null)
  const [reference, setReference] = useState('')
  const [expiration, setExpiration] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  function fermer() {
    setAction(null)
    setErreurs({})
  }

  async function appliquer(type: Action['type'], acces: AccesRegistre, corps?: object) {
    setEnvoi(true)
    try {
      await confirmer(
        api(`/registre/acces/${acces.id}/${type}`, { method: 'POST', corps }),
        type === 'valider' ? 'Accès validé' : type === 'refuser' ? 'Demande refusée' : 'Accès retiré',
      )
      setMessage({
        ton: 'succes',
        texte:
          type === 'valider'
            ? `Accès validé pour ${acces.organisme}.`
            : type === 'refuser'
              ? `Demande de ${acces.organisme} refusée.`
              : `Accès de ${acces.organisme} retiré : ses demandes sont refusées dès maintenant.`,
      })
      setReference('')
      setExpiration('')
      fermer()
      etat.recharger()
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.erreurs).length > 0) {
        setErreurs(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])))
      } else {
        setMessage({ ton: 'erreur', texte: messageErreur(err) })
        fermer()
      }
    } finally {
      setEnvoi(false)
    }
  }

  function valider(e: FormEvent) {
    e.preventDefault()
    if (!action) return
    if (!reference.trim()) {
      setErreurs({ reference_juridique: 'La référence de la convention ou du texte est obligatoire.' })
      return
    }
    void appliquer('valider', action.acces, {
      reference_juridique: reference.trim(),
      expire_le: expiration ? `${expiration} 23:59:59` : undefined,
    })
  }

  return (
    <>
      <PageHeader
        titre="Accès à mon registre"
        description="La source garde la main : un droit demandé par une administration n'a d'effet qu'une fois validé par vous."
      />

      {message && (
        <div className="mb-6">
          <Notice ton={message.ton}>{message.texte}</Notice>
        </div>
      )}

      <div className="mb-5">
        <Segmente<Onglet>
          legende="Type d'accès"
          valeur={onglet}
          onChange={(v) => {
            setOnglet(v)
            setMessage(null)
          }}
          options={[
            { valeur: 'en_attente', libelle: 'À valider' },
            { valeur: 'valide', libelle: 'Accordés' },
          ]}
        />
      </div>

      <Ressource etat={etat} lignes={3}>
        {(r) =>
          r.donnees.length === 0 ? (
            <Vide
              titre={onglet === 'en_attente' ? 'Aucune demande en attente' : 'Aucun accès accordé'}
              texte={
                onglet === 'en_attente'
                  ? "Quand une administration demandera un accès à l'une de vos questions, elle apparaîtra ici."
                  : "Les accès que vous avez validés apparaîtront ici."
              }
            />
          ) : (
            <ul className="space-y-4">
              {r.donnees.map((a) => (
                <li key={a.id} className="rounded-panel border border-border bg-surface p-5">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h2 className="text-lg font-semibold text-text">{a.organisme}</h2>
                      <p className="mt-1 text-base text-text">
                        demande : <strong className="font-semibold">« {a.question} »</strong>
                      </p>
                      <p className="text-base text-muted">Finalité : {a.finalite}</p>
                    </div>
                    <BadgeEtatDroit etat={a.etat} />
                  </div>

                  {a.justification && (
                    <p className="mt-3 rounded-control bg-surface-2 p-3 text-base text-text">
                      <span className="text-sm text-muted">Justification : </span>
                      {a.justification}
                    </p>
                  )}
                  {onglet === 'valide' && (
                    <p className="mt-3 text-sm text-muted">
                      {a.reference_juridique ?? 'Sans référence'}
                      {a.expire_le ? `, jusqu'au ${dateLongue(a.expire_le)}` : ', sans limite de durée'}
                    </p>
                  )}

                  <div className="mt-4 flex flex-wrap gap-3">
                    {onglet === 'en_attente' ? (
                      <>
                        <Bouton onClick={() => setAction({ type: 'valider', acces: a })}>Valider l'accès</Bouton>
                        <Bouton variante="secondaire" onClick={() => setAction({ type: 'refuser', acces: a })}>
                          Refuser
                        </Bouton>
                      </>
                    ) : (
                      <Bouton variante="secondaire" onClick={() => setAction({ type: 'retirer', acces: a })}>
                        Retirer l'accès
                      </Bouton>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )
        }
      </Ressource>

      <Dialogue
        ouvert={action?.type === 'valider'}
        onFermer={fermer}
        titre="Valider l'accès"
        description={action ? `${action.acces.organisme} pourra poser « ${action.acces.question} ».` : undefined}
        pied={
          <>
            <Bouton variante="secondaire" onClick={fermer}>
              Annuler
            </Bouton>
            <Bouton type="submit" form="formulaire-validation" chargement={envoi}>
              Valider l'accès
            </Bouton>
          </>
        }
      >
        <form id="formulaire-validation" onSubmit={valider} noValidate className="space-y-5">
          <Input
            libelle="Référence juridique"
            aide="La convention d'échange ou le texte qui fonde cet accès."
            value={reference}
            onChange={(e) => setReference(e.target.value)}
            erreur={erreurs.reference_juridique}
            required
          />
          <Input
            libelle="Valable jusqu'au"
            type="date"
            aide="Facultatif. Sans date, l'accès dure jusqu'à ce que vous le retiriez."
            value={expiration}
            onChange={(e) => setExpiration(e.target.value)}
            erreur={erreurs.expire_le}
          />
        </form>
      </Dialogue>

      <Confirmation
        ouvert={action?.type === 'refuser' || action?.type === 'retirer'}
        titre={action?.type === 'retirer' ? "Retirer l'accès ?" : 'Refuser cette demande ?'}
        libelleAction={action?.type === 'retirer' ? "Retirer l'accès" : 'Refuser la demande'}
        danger
        envoi={envoi}
        onConfirmer={() => action && void appliquer(action.type, action.acces)}
        onFermer={fermer}
      >
        {action && (
          <p>
            {action.type === 'retirer'
              ? `${action.acces.organisme} ne pourra plus poser « ${action.acces.question} », immédiatement.`
              : `La demande de ${action.acces.organisme} sera supprimée.`}
          </p>
        )}
      </Confirmation>
    </>
  )
}
