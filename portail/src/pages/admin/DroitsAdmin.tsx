import { useState } from 'react'
import type { FormEvent } from 'react'
import { Bouton } from '../../components/Bouton'
import { Input, Select } from '../../components/Champs'
import { Ressource } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Panneau } from '../../components/Panneau'
import { PageHeader } from '../../components/PageHeader'
import { ApiError, api, messageErreur } from '../../lib/api'
import type { Liste, OrganismeAdmin, QuestionAdmin } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function DroitsAdmin() {
  const organismes = useApi<Liste<OrganismeAdmin>>('/admin/organisations')
  const questions = useApi<Liste<QuestionAdmin>>('/admin/catalogue')
  const [organisme, setOrganisme] = useState('')
  const [question, setQuestion] = useState('')
  const [finalite, setFinalite] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  const demandeurs = (organismes.donnees?.donnees ?? []).filter((o) => o.roles.includes('demandeuse'))
  const organismeActif = organisme || demandeurs[0]?.sigle || ''
  const questionActive = question || questions.donnees?.donnees[0]?.code || ''

  async function creer(e: FormEvent) {
    e.preventDefault()
    if (!finalite.trim()) {
      setErreurs({ finalite: 'Indiquez la finalité.' })
      return
    }
    setErreurs({})
    setEnvoi(true)
    setMessage(null)
    try {
      await api('/admin/droits', {
        method: 'POST',
        corps: { organisme: organismeActif, question: questionActive, finalite: finalite.trim() },
      })
      setMessage({
        ton: 'succes',
        texte: "Droit enregistré. Il n'a aucun effet tant que le gestionnaire du registre ne l'a pas validé.",
      })
      setFinalite('')
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.erreurs).length > 0) {
        setErreurs(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])))
      } else {
        setMessage({ ton: 'erreur', texte: messageErreur(err) })
      }
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <PageHeader
        titre="Droits"
        description="Accordez à un organisme le droit de poser une question pour une finalité. La source doit ensuite le valider."
      />

      <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <Ressource etat={organismes} lignes={3}>
          {() => (
            <Panneau titre="Nouveau droit">
              <form onSubmit={creer} noValidate className="space-y-5">
                {message && <Notice ton={message.ton}>{message.texte}</Notice>}
                <Select libelle="Organisme demandeur" value={organismeActif} onChange={(e) => setOrganisme(e.target.value)} erreur={erreurs.organisme}>
                  {demandeurs.map((o) => (
                    <option key={o.id} value={o.sigle}>
                      {o.nom}
                    </option>
                  ))}
                </Select>
                <Select libelle="Question" value={questionActive} onChange={(e) => setQuestion(e.target.value)} erreur={erreurs.question}>
                  {(questions.donnees?.donnees ?? []).map((q) => (
                    <option key={q.code} value={q.code}>
                      {q.libelle}
                    </option>
                  ))}
                </Select>
                <Input
                  libelle="Finalité"
                  aide="Elle doit être identique à celle que l'agent indiquera dans sa demande."
                  value={finalite}
                  onChange={(e) => setFinalite(e.target.value)}
                  erreur={erreurs.finalite}
                  required
                />
                <Bouton type="submit" chargement={envoi}>
                  Enregistrer le droit
                </Bouton>
              </form>
            </Panneau>
          )}
        </Ressource>

        <Panneau titre="Qui décide ?">
          <ul className="space-y-3 text-base text-text">
            <li>Vous préparez le droit. Il reste inactif.</li>
            <li>Le gestionnaire du registre concerné le valide, avec la référence de la convention.</li>
            <li>Il peut fixer une date de fin, ou retirer le droit à tout moment.</li>
            <li>Vous ne voyez jamais les données des citoyens.</li>
          </ul>
        </Panneau>
      </div>
    </>
  )
}
