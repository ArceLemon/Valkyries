import { useMemo, useState } from 'react'
import { Input } from '../components/Champs'
import { Ressource, Vide } from '../components/Etats'
import { BadgeRegime, BadgeSensibilite } from '../components/Pastille'
import { PageHeader } from '../components/PageHeader'
import { dureeValidite, libelleRegistre } from '../lib/format'
import type { Liste, QuestionCatalogue } from '../lib/types'
import { useApi } from '../lib/useApi'

export function PageCatalogue() {
  const etat = useApi<Liste<QuestionCatalogue>>('/catalogue')
  const [recherche, setRecherche] = useState('')

  const questions = useMemo(() => {
    const liste = etat.donnees?.donnees ?? []
    const terme = recherche.trim().toLowerCase()
    if (!terme) return liste
    return liste.filter((q) =>
      [q.libelle, q.description ?? '', q.source ?? '', q.code].some((t) => t.toLowerCase().includes(terme)),
    )
  }, [etat.donnees, recherche])

  return (
    <>
      <PageHeader
        titre="Catalogue des questions"
        description="Ce qu'une administration peut demander. Chaque réponse est un simple oui, non ou impossible : jamais la donnée elle-même."
      />

      <div className="mb-6 max-w-md">
        <Input
          libelle="Rechercher une question"
          type="search"
          value={recherche}
          onChange={(e) => setRecherche(e.target.value)}
        />
      </div>

      <Ressource etat={etat} lignes={4}>
        {() =>
          questions.length === 0 ? (
            <Vide titre="Aucune question ne correspond" texte="Essayez un autre mot, ou videz la recherche." />
          ) : (
            <ul className="divide-y divide-border rounded-panel border border-border bg-surface">
              {questions.map((q) => (
                <li key={q.code} className="grid gap-4 p-5 lg:grid-cols-[1fr_auto]">
                  <div className="min-w-0">
                    <h2 className="text-lg font-semibold text-text">{q.libelle}</h2>
                    {q.description && <p className="mt-1 max-w-[65ch] text-base text-muted">{q.description}</p>}
                    <dl className="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                      <div className="flex gap-2">
                        <dt className="text-muted">Source :</dt>
                        <dd className="text-text">{q.source ?? libelleRegistre(q.registre)}</dd>
                      </div>
                      <div className="flex gap-2">
                        <dt className="text-muted">Preuve valable :</dt>
                        <dd className="text-text">{dureeValidite(q.validite_minutes)}</dd>
                      </div>
                      {q.parametree && (
                        <div className="flex gap-2 sm:col-span-2">
                          <dt className="text-muted">Paramètre :</dt>
                          <dd className="text-text">à préciser à chaque demande</dd>
                        </div>
                      )}
                      {q.base_juridique && (
                        <div className="flex gap-2 sm:col-span-2">
                          <dt className="shrink-0 text-muted">Base juridique :</dt>
                          <dd className="text-text">{q.base_juridique}</dd>
                        </div>
                      )}
                    </dl>
                  </div>
                  <div className="flex flex-wrap items-start gap-2 lg:max-w-56 lg:flex-col lg:items-end">
                    <BadgeRegime regime={q.regime} />
                    <BadgeSensibilite sensibilite={q.sensibilite} />
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
