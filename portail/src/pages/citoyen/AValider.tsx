import { useState } from 'react'
import { Bouton } from '../../components/Bouton'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { BadgeSensibilite } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { api, messageErreur } from '../../lib/api'
import { tempsRestant } from '../../lib/format'
import type { ConsentementACiter, Liste } from '../../lib/types'
import { useApi } from '../../lib/useApi'
import { useValidation } from '../../lib/validationContexte'

export function AValider() {
  const valider = useValidation()
  const etat = useApi<Liste<ConsentementACiter>>('/citoyen/demandes-a-valider', () => 15000)
  const [enCours, setEnCours] = useState<string | null>(null)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  async function decider(c: ConsentementACiter, action: 'accepter' | 'refuser') {
    setEnCours(`${c.id}-${action}`)
    setMessage(null)
    try {
      await valider(
        api(`/citoyen/consentements/${c.id}/${action}`, { method: 'POST' }),
        action === 'accepter' ? 'Accord validé' : 'Refus enregistré',
      )
      setMessage({
        ton: 'succes',
        texte:
          action === 'accepter'
            ? 'Merci. Votre accord est enregistré : le registre a été interrogé, et seule la réponse a été transmise.'
            : "Votre refus est enregistré. Le registre n'a pas été interrogé.",
      })
      etat.recharger()
    } catch (e) {
      setMessage({ ton: 'erreur', texte: messageErreur(e) })
    } finally {
      setEnCours(null)
    }
  }

  return (
    <>
      <PageHeader
        titre="Demandes à valider"
        description="Une administration vous demande votre accord. Vous décidez : sans votre réponse, rien n'est consulté."
      />

      {message && (
        <div className="mb-6">
          <Notice ton={message.ton}>{message.texte}</Notice>
        </div>
      )}

      <Ressource etat={etat} lignes={2}>
        {(r) =>
          r.donnees.length === 0 ? (
            <Vide
              titre="Aucune demande en attente"
              texte="Quand une administration aura besoin de votre accord, la demande apparaîtra ici."
            />
          ) : (
            <ul className="space-y-5">
              {r.donnees.map((c) => (
                <li key={c.id} className="rounded-panel border border-border bg-surface p-5">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <h2 className="text-lg font-semibold text-text">{c.organisme}</h2>
                    <BadgeSensibilite sensibilite={c.sensibilite} />
                  </div>
                  <p className="mt-3 text-base text-text">
                    demande : <strong className="font-semibold">« {c.question} »</strong>
                  </p>
                  <p className="mt-1 text-base text-muted">Pour : {c.finalite}</p>
                  <p className="mt-3 text-sm text-muted">
                    Sans réponse de votre part, la demande expire dans {tempsRestant(c.expire_le)}.
                  </p>
                  {c.sensibilite === 'elevee' && (
                    <p className="mt-3 rounded-control bg-surface-2 p-3 text-sm text-text">
                      Seule la réponse oui ou non sera transmise. Jamais le détail.
                    </p>
                  )}
                  <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    <Bouton
                      onClick={() => void decider(c, 'accepter')}
                      chargement={enCours === `${c.id}-accepter`}
                      disabled={enCours !== null}
                    >
                      Accepter
                    </Bouton>
                    <Bouton
                      variante="secondaire"
                      onClick={() => void decider(c, 'refuser')}
                      chargement={enCours === `${c.id}-refuser`}
                      disabled={enCours !== null}
                    >
                      Refuser
                    </Bouton>
                  </div>
                </li>
              ))}
            </ul>
          )
        }
      </Ressource>
    </>
  )
}
