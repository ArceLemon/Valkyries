import { CheckCircle, Timer, XCircle, MinusCircle } from '@phosphor-icons/react'
import { useState } from 'react'
import { Bouton } from '../../components/Bouton'
import { Dialogue } from '../../components/Dialogue'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Pastille } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { api, messageErreur } from '../../lib/api'
import { dateHeure } from '../../lib/format'
import type { ConsentementCitoyen, Liste } from '../../lib/types'
import { useApi } from '../../lib/useApi'
import { useValidation } from '../../lib/validationContexte'

function BadgeConsentement({ statut }: { statut: ConsentementCitoyen['statut'] }) {
  if (statut === 'accepte') return <Pastille ton="ok" icone={CheckCircle}>Accepté</Pastille>
  if (statut === 'refuse') return <Pastille ton="refus" icone={XCircle}>Refusé</Pastille>
  if (statut === 'retire') return <Pastille ton="neutre" icone={MinusCircle}>Retiré</Pastille>
  return <Pastille ton="neutre" icone={Timer}>Expiré</Pastille>
}

export function Consentements() {
  const valider = useValidation()
  const etat = useApi<Liste<ConsentementCitoyen>>('/citoyen/consentements')
  const [aRetirer, setARetirer] = useState<ConsentementCitoyen | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  async function retirer() {
    if (!aRetirer) return
    setEnvoi(true)
    try {
      await valider(api(`/citoyen/consentements/${aRetirer.id}/retirer`, { method: 'POST' }), 'Accord retiré')
      setMessage({ ton: 'succes', texte: "Votre accord est retiré. L'attestation déjà émise n'est plus valable." })
      setARetirer(null)
      etat.recharger()
    } catch (e) {
      setMessage({ ton: 'erreur', texte: messageErreur(e) })
      setARetirer(null)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <PageHeader
        titre="Mes consentements"
        description="Les accords que vous avez donnés ou refusés. Vous pouvez retirer un accord à tout moment."
      />

      {message && (
        <div className="mb-6">
          <Notice ton={message.ton}>{message.texte}</Notice>
        </div>
      )}

      <Ressource etat={etat} lignes={3}>
        {(r) =>
          r.donnees.length === 0 ? (
            <Vide
              titre="Aucun consentement pour l'instant"
              texte="Vos décisions apparaîtront ici dès que vous aurez répondu à une demande."
            />
          ) : (
            <ul className="divide-y divide-border rounded-panel border border-border bg-surface">
              {r.donnees.map((c) => (
                <li key={c.id} className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <p className="text-base font-semibold text-text">{c.organisme}</p>
                    <p className="text-base text-text">« {c.question} »</p>
                    <p className="text-sm text-muted">
                      {c.finalite}
                      {c.decide_le ? `, ${dateHeure(c.decide_le)}` : ''}
                    </p>
                  </div>
                  <div className="flex flex-wrap items-center gap-3">
                    <BadgeConsentement statut={c.statut} />
                    {c.statut === 'accepte' && (
                      <Bouton variante="secondaire" taille="s" onClick={() => setARetirer(c)}>
                        Retirer mon accord
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
        ouvert={aRetirer !== null}
        onFermer={() => setARetirer(null)}
        titre="Retirer votre accord ?"
        description="Cette action est immédiate."
        pied={
          <>
            <Bouton variante="secondaire" onClick={() => setARetirer(null)}>
              Garder mon accord
            </Bouton>
            <Bouton variante="danger" onClick={() => void retirer()} chargement={envoi}>
              Retirer mon accord
            </Bouton>
          </>
        }
      >
        {aRetirer && (
          <p className="text-base text-text">
            L'attestation obtenue grâce à votre accord pour « {aRetirer.question} » ({aRetirer.organisme}) sera
            révoquée : quiconque la vérifiera verra qu'elle n'est plus valable.
          </p>
        )}
      </Dialogue>
    </>
  )
}
