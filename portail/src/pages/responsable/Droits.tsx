import { Plus } from '@phosphor-icons/react'
import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { Bouton } from '../../components/Bouton'
import { Input, Select, Textarea } from '../../components/Champs'
import { Dialogue } from '../../components/Dialogue'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { BadgeEtatDroit } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Tableau, Td, Th } from '../../components/Tableau'
import { ApiError, api, messageErreur } from '../../lib/api'
import { dateLongue } from '../../lib/format'
import type { DroitOrganisme, Liste, QuestionCatalogue } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function Droits() {
  const etat = useApi<Liste<DroitOrganisme>>('/organisme/droits')
  const catalogue = useApi<Liste<QuestionCatalogue>>('/catalogue')
  const [ouvert, setOuvert] = useState(false)
  const [question, setQuestion] = useState('')
  const [finalite, setFinalite] = useState('')
  const [justification, setJustification] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  const questions = useMemo(() => catalogue.donnees?.donnees ?? [], [catalogue.donnees])
  const finalites = useMemo(() => [...new Set((etat.donnees?.donnees ?? []).map((d) => d.finalite))], [etat.donnees])
  const questionActive = question || questions[0]?.code || ''

  function fermer() {
    setOuvert(false)
    setErreurs({})
  }

  async function demander(e: FormEvent) {
    e.preventDefault()
    const trouvees: Record<string, string> = {}
    if (!finalite.trim()) trouvees.finalite = 'Indiquez la finalité.'
    if (justification.trim().length < 10) trouvees.justification = 'Expliquez pourquoi, en quelques mots (10 caractères au moins).'
    setErreurs(trouvees)
    if (Object.keys(trouvees).length > 0) return

    setEnvoi(true)
    try {
      await api('/organisme/droits', {
        method: 'POST',
        corps: { question: questionActive, finalite: finalite.trim(), justification: justification.trim() },
      })
      setMessage({
        ton: 'succes',
        texte: "Demande envoyée. Elle n'aura d'effet qu'une fois validée par le gestionnaire du registre concerné.",
      })
      setFinalite('')
      setJustification('')
      fermer()
      etat.recharger()
    } catch (err) {
      if (err instanceof ApiError && err.status === 409) {
        setErreurs({ finalite: 'Une demande ou un droit identique existe déjà.' })
      } else if (err instanceof ApiError && Object.keys(err.erreurs).length > 0) {
        setErreurs(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])))
      } else {
        setErreurs({ justification: messageErreur(err) })
      }
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <PageHeader
        titre="Droits de l'organisme"
        description="Ce que votre organisme peut demander, et pour quelle finalité. La source garde la main : elle valide chaque droit."
        actions={
          <Bouton onClick={() => setOuvert(true)} icone={<Plus size={20} />}>
            Demander un accès
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
            <Vide titre="Aucun droit" texte="Demandez un accès à une question : le gestionnaire du registre le validera." />
          ) : (
            <Tableau legende="Droits de l'organisme">
              <thead>
                <tr>
                  <Th>Question</Th>
                  <Th>État</Th>
                  <Th className="hidden md:table-cell">Référence juridique</Th>
                  <Th className="hidden lg:table-cell">Valable jusqu'au</Th>
                </tr>
              </thead>
              <tbody>
                {r.donnees.map((d) => (
                  <tr key={d.id}>
                    <Td>
                      <span className="font-medium text-text">{d.libelle}</span>
                      <span className="mt-0.5 block text-muted">{d.finalite}</span>
                    </Td>
                    <Td>
                      <BadgeEtatDroit etat={d.etat} />
                    </Td>
                    <Td className="hidden text-muted md:table-cell">{d.reference_juridique ?? '-'}</Td>
                    <Td className="hidden whitespace-nowrap text-muted lg:table-cell">
                      {d.expire_le ? dateLongue(d.expire_le) : 'Sans limite'}
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
        titre="Demander un accès"
        description="Le gestionnaire du registre examinera votre demande."
        pied={
          <>
            <Bouton variante="secondaire" onClick={fermer}>
              Annuler
            </Bouton>
            <Bouton type="submit" form="formulaire-droit" chargement={envoi}>
              Envoyer la demande
            </Bouton>
          </>
        }
      >
        <form id="formulaire-droit" onSubmit={demander} noValidate className="space-y-5">
          <Select libelle="Question" value={questionActive} onChange={(e) => setQuestion(e.target.value)}>
            {questions.map((q) => (
              <option key={q.code} value={q.code}>
                {q.libelle}
              </option>
            ))}
          </Select>
          <div>
            <Input
              libelle="Finalité"
              aide="La raison de la demande, par exemple « Immatriculation d'une entreprise »."
              list="finalites-connues"
              value={finalite}
              onChange={(e) => setFinalite(e.target.value)}
              erreur={erreurs.finalite}
              required
            />
            <datalist id="finalites-connues">
              {finalites.map((f) => (
                <option key={f} value={f} />
              ))}
            </datalist>
          </div>
          <Textarea
            libelle="Justification"
            aide="Pourquoi votre organisme a besoin de cette information."
            value={justification}
            onChange={(e) => setJustification(e.target.value)}
            erreur={erreurs.justification}
            required
          />
        </form>
      </Dialogue>
    </>
  )
}
