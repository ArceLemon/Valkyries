import { Eye } from '@phosphor-icons/react'
import { useMemo, useState } from 'react'
import { Select } from '../../components/Champs'
import { EvenementCitoyen } from '../../components/EvenementCitoyen'
import { Ressource, Vide } from '../../components/Etats'
import { PageHeader } from '../../components/PageHeader'
import { dateLongue, jour } from '../../lib/format'
import type { ConsultationCitoyen, Liste } from '../../lib/types'
import { useApi } from '../../lib/useApi'

/** « Qui a consulté mes données » : la page centrale de l'espace citoyen. */
export function Historique() {
  const etat = useApi<Liste<ConsultationCitoyen>>('/citoyen/historique', () => 20000)
  const [organisme, setOrganisme] = useState('')

  const lignes = useMemo(() => etat.donnees?.donnees ?? [], [etat.donnees])
  const organismes = useMemo(
    () => [...new Set(lignes.map((l) => l.organisme).filter((o): o is string => !!o))].sort(),
    [lignes],
  )

  const groupes = useMemo(() => {
    const visibles = lignes.filter((l) => !organisme || l.organisme === organisme)
    const map = new Map<string, ConsultationCitoyen[]>()
    for (const l of visibles) {
      const cle = jour(l.date)
      map.set(cle, [...(map.get(cle) ?? []), l])
    }
    return [...map.entries()]
  }, [lignes, organisme])

  return (
    <>
      <PageHeader
        titre="Qui a consulté mes données"
        description="Chaque vérification faite à votre sujet : qui, quand, pour quoi, et la réponse donnée. Cette liste ne peut pas être modifiée en secret."
      />

      <Ressource etat={etat} lignes={4}>
        {() =>
          lignes.length === 0 ? (
            <Vide
              icone={Eye}
              titre="Personne n'a consulté vos données"
              texte="Dès qu'une administration vérifiera une information vous concernant, elle apparaîtra ici."
            />
          ) : (
            <>
              <div className="mb-6 max-w-sm">
                <Select libelle="Organisme" value={organisme} onChange={(e) => setOrganisme(e.target.value)}>
                  <option value="">Tous les organismes</option>
                  {organismes.map((o) => (
                    <option key={o} value={o}>
                      {o}
                    </option>
                  ))}
                </Select>
              </div>

              {groupes.length === 0 ? (
                <Vide titre="Aucune consultation pour cet organisme" />
              ) : (
                <div className="space-y-8">
                  {groupes.map(([date, evenements]) => (
                    <section key={date} aria-labelledby={`jour-${date}`}>
                      <h2 id={`jour-${date}`} className="mb-3 text-sm font-medium text-muted">
                        {dateLongue(evenements[0].date)}
                      </h2>
                      <ul className="divide-y divide-border rounded-panel border border-border bg-surface p-5">
                        {evenements.map((e, i) => (
                          <EvenementCitoyen key={`${e.date}-${i}`} e={e} />
                        ))}
                      </ul>
                    </section>
                  ))}
                </div>
              )}
            </>
          )
        }
      </Ressource>
    </>
  )
}
