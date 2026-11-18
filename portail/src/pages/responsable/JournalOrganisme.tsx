import { useMemo, useState } from 'react'
import { Select } from '../../components/Champs'
import { Ressource, Vide } from '../../components/Etats'
import { PageHeader } from '../../components/PageHeader'
import { TableJournal } from '../../components/TableJournal'
import { libelleAction } from '../../lib/format'
import type { LigneJournal, Liste, QuestionCatalogue } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function JournalOrganisme() {
  const etat = useApi<Liste<LigneJournal>>('/organisme/journal')
  const catalogue = useApi<Liste<QuestionCatalogue>>('/catalogue')
  const [action, setAction] = useState('')

  const questions = useMemo(
    () => Object.fromEntries((catalogue.donnees?.donnees ?? []).map((q) => [q.code, q.libelle])),
    [catalogue.donnees],
  )

  const lignes = useMemo(() => etat.donnees?.donnees ?? [], [etat.donnees])
  const actions = useMemo(() => [...new Set(lignes.map((l) => l.action))].sort(), [lignes])
  const visibles = lignes.filter((l) => !action || l.action === action)

  return (
    <>
      <PageHeader
        titre="Journal de l'organisme"
        description="Tout ce que les agents de votre organisme ont fait, du plus récent au plus ancien. Aucune donnée de citoyen n'y figure."
      />

      <Ressource etat={etat} lignes={5}>
        {() =>
          lignes.length === 0 ? (
            <Vide titre="Le journal est vide" texte="Les actions de votre organisme apparaîtront ici." />
          ) : (
            <>
              <div className="mb-5 max-w-sm">
                <Select libelle="Type d'événement" value={action} onChange={(e) => setAction(e.target.value)}>
                  <option value="">Tous les événements</option>
                  {actions.map((a) => (
                    <option key={a} value={a}>
                      {libelleAction(a)}
                    </option>
                  ))}
                </Select>
              </div>
              <TableJournal lignes={visibles} questions={questions} />
            </>
          )
        }
      </Ressource>
    </>
  )
}
