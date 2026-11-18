import { useState } from 'react'
import type { FormEvent } from 'react'
import { Bouton } from '../../components/Bouton'
import { Case, Input, Select } from '../../components/Champs'
import { Dialogue } from '../../components/Dialogue'
import { Ressource } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { BadgeRegime, BadgeSensibilite, Pastille } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Tableau, Td, Th } from '../../components/Tableau'
import { ApiError, api, messageErreur } from '../../lib/api'
import { dureeValidite, libelleRegistre } from '../../lib/format'
import type { Liste, QuestionAdmin } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function CatalogueAdmin() {
  const etat = useApi<Liste<QuestionAdmin>>('/admin/catalogue')
  const [edition, setEdition] = useState<QuestionAdmin | null>(null)
  const [libelle, setLibelle] = useState('')
  const [validite, setValidite] = useState('')
  const [regime, setRegime] = useState<'base_legale' | 'consentement'>('base_legale')
  const [sensibilite, setSensibilite] = useState<'normale' | 'elevee'>('normale')
  const [regle, setRegle] = useState('')
  const [publie, setPublie] = useState(true)
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  function ouvrir(q: QuestionAdmin) {
    setEdition(q)
    setLibelle(q.libelle)
    setValidite(String(q.validite_minutes))
    setRegime(q.regime)
    setSensibilite(q.sensibilite)
    setRegle(q.regle)
    setPublie(q.publie)
    setErreurs({})
  }

  async function enregistrer(e: FormEvent) {
    e.preventDefault()
    if (!edition) return
    const trouvees: Record<string, string> = {}
    if (!libelle.trim()) trouvees.libelle = 'Le libellé est obligatoire.'
    const minutes = Number(validite)
    if (!Number.isInteger(minutes) || minutes < 1) trouvees.validite_minutes = 'Indiquez une durée en minutes (au moins 1).'
    setErreurs(trouvees)
    if (Object.keys(trouvees).length > 0) return

    setEnvoi(true)
    try {
      await api(`/admin/catalogue/${edition.id}`, {
        method: 'PATCH',
        corps: {
          libelle: libelle.trim(),
          validite_minutes: minutes,
          regime,
          sensibilite,
          regle: regle.trim(),
          publie,
        },
      })
      setMessage({ ton: 'succes', texte: `« ${libelle.trim()} » est mise à jour. L'ancienne et la nouvelle valeur sont inscrites au journal.` })
      setEdition(null)
      etat.recharger()
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.erreurs).length > 0) {
        setErreurs(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])))
      } else if (err instanceof ApiError && err.status === 422) {
        setErreurs({ regle: err.message })
      } else {
        setErreurs({ libelle: messageErreur(err) })
      }
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <PageHeader
        titre="Catalogue des questions"
        description="Les questions auxquelles le système sait répondre. Toute modification est journalisée, avec l'ancienne et la nouvelle valeur."
      />

      {message && (
        <div className="mb-6">
          <Notice ton={message.ton}>{message.texte}</Notice>
        </div>
      )}

      <Ressource etat={etat} lignes={5}>
        {(r) => (
          <Tableau legende="Questions du catalogue">
            <thead>
              <tr>
                <Th>Question</Th>
                <Th className="hidden md:table-cell">Source</Th>
                <Th className="hidden md:table-cell">Régime</Th>
                <Th className="hidden lg:table-cell">Preuve</Th>
                <Th>
                  <span className="sr-only">Actions</span>
                </Th>
              </tr>
            </thead>
            <tbody>
              {r.donnees.map((q) => (
                <tr key={q.id}>
                  <Td>
                    <span className="font-medium text-text">{q.libelle}</span>
                    <span className="mono mt-0.5 block text-muted">{q.code}</span>
                    {!q.publie && (
                      <span className="mt-1 inline-block">
                        <Pastille ton="neutre">Non publiée</Pastille>
                      </span>
                    )}
                  </Td>
                  <Td className="hidden md:table-cell">{libelleRegistre(q.registre)}</Td>
                  <Td className="hidden md:table-cell">
                    <div className="flex flex-col items-start gap-1">
                      <BadgeRegime regime={q.regime} />
                      <BadgeSensibilite sensibilite={q.sensibilite} />
                    </div>
                  </Td>
                  <Td className="hidden text-muted lg:table-cell">{dureeValidite(q.validite_minutes)}</Td>
                  <Td className="text-right">
                    <Bouton variante="secondaire" taille="s" onClick={() => ouvrir(q)}>
                      Modifier
                    </Bouton>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Tableau>
        )}
      </Ressource>

      <Dialogue
        ouvert={edition !== null}
        onFermer={() => setEdition(null)}
        titre="Modifier la question"
        description={edition ? `Code : ${edition.code}` : undefined}
        pied={
          <>
            <Bouton variante="secondaire" onClick={() => setEdition(null)}>
              Annuler
            </Bouton>
            <Bouton type="submit" form="formulaire-question" chargement={envoi}>
              Enregistrer
            </Bouton>
          </>
        }
      >
        <form id="formulaire-question" onSubmit={enregistrer} noValidate className="space-y-5">
          <Input libelle="Libellé" value={libelle} onChange={(e) => setLibelle(e.target.value)} erreur={erreurs.libelle} required />
          <Select libelle="Régime" value={regime} onChange={(e) => setRegime(e.target.value as typeof regime)}>
            <option value="base_legale">Mission légale : le citoyen est informé</option>
            <option value="consentement">Consentement du citoyen requis</option>
          </Select>
          <Select libelle="Sensibilité" value={sensibilite} onChange={(e) => setSensibilite(e.target.value as typeof sensibilite)}>
            <option value="normale">Normale</option>
            <option value="elevee">Élevée</option>
          </Select>
          <Input
            libelle="Durée de validité de la preuve (minutes)"
            type="number"
            min={1}
            inputMode="numeric"
            value={validite}
            onChange={(e) => setValidite(e.target.value)}
            erreur={erreurs.validite_minutes}
            required
          />
          <Input
            libelle="Règle de calcul"
            aide="Fonctions autorisées : age_gte, equals, is_empty, in_list, date_gte. Aucun autre code n'est exécuté."
            spellCheck={false}
            className="mono"
            value={regle}
            onChange={(e) => setRegle(e.target.value)}
            erreur={erreurs.regle}
          />
          <Case libelle="Publiée au catalogue" aide="Une question non publiée ne peut plus être posée." checked={publie} onChange={(e) => setPublie(e.target.checked)} />
        </form>
      </Dialogue>
    </>
  )
}
