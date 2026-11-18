import { PlusCircle } from '@phosphor-icons/react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { classesBouton } from '../../components/Bouton'
import { Input } from '../../components/Champs'
import { Ressource, Vide } from '../../components/Etats'
import { BadgeReponse, BadgeStatut } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Segmente } from '../../components/Segmente'
import { Tableau, Td, Th } from '../../components/Tableau'
import { dateHeure, estEnCours } from '../../lib/format'
import type { Demande, Liste } from '../../lib/types'
import { useApi } from '../../lib/useApi'

type Filtre = 'toutes' | 'en_cours' | 'terminees' | 'autres'

export function MesDemandes() {
  const etat = useApi<Liste<Demande>>('/demandes', (d) =>
    d?.donnees.some((x) => estEnCours(x.statut)) ? 10000 : null,
  )
  const [filtre, setFiltre] = useState<Filtre>('toutes')
  const [recherche, setRecherche] = useState('')

  const toutes = useMemo(() => etat.donnees?.donnees ?? [], [etat.donnees])

  const comptes = useMemo(
    () => ({
      toutes: toutes.length,
      en_cours: toutes.filter((d) => estEnCours(d.statut)).length,
      terminees: toutes.filter((d) => d.statut === 'terminee').length,
      autres: toutes.filter((d) => !estEnCours(d.statut) && d.statut !== 'terminee').length,
    }),
    [toutes],
  )

  const visibles = useMemo(() => {
    const terme = recherche.trim().toLowerCase()
    return toutes.filter((d) => {
      if (filtre === 'en_cours' && !estEnCours(d.statut)) return false
      if (filtre === 'terminees' && d.statut !== 'terminee') return false
      if (filtre === 'autres' && (estEnCours(d.statut) || d.statut === 'terminee')) return false
      return !terme || `${d.libelle} ${d.finalite}`.toLowerCase().includes(terme)
    })
  }, [toutes, filtre, recherche])

  return (
    <>
      <PageHeader
        titre="Mes demandes"
        description="Les questions posées, et où elles en sont."
        actions={
          <Link to="/demandes/nouvelle" className={classesBouton('primaire')}>
            <PlusCircle size={20} aria-hidden="true" />
            Nouvelle demande
          </Link>
        }
      />

      <div className="mb-5 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <Segmente<Filtre>
          legende="Filtrer par état"
          valeur={filtre}
          onChange={setFiltre}
          options={[
            { valeur: 'toutes', libelle: 'Toutes', compte: comptes.toutes },
            { valeur: 'en_cours', libelle: 'À suivre', compte: comptes.en_cours },
            { valeur: 'terminees', libelle: 'Terminées', compte: comptes.terminees },
            { valeur: 'autres', libelle: 'Refusées ou expirées', compte: comptes.autres },
          ]}
        />
        <div className="w-full md:max-w-xs">
          <Input libelle="Rechercher" type="search" value={recherche} onChange={(e) => setRecherche(e.target.value)} />
        </div>
      </div>

      <Ressource etat={etat} lignes={5}>
        {() =>
          toutes.length === 0 ? (
            <Vide
              titre="Aucune demande pour l'instant"
              texte="Posez votre première question : vous recevrez un oui ou un non, sans papier."
              action={
                <Link to="/demandes/nouvelle" className={classesBouton('primaire')}>
                  Poser une question
                </Link>
              }
            />
          ) : visibles.length === 0 ? (
            <Vide titre="Aucune demande ne correspond" texte="Changez de filtre ou videz la recherche." />
          ) : (
            <Tableau legende="Mes demandes, de la plus récente à la plus ancienne">
              <thead>
                <tr>
                  <Th>Question</Th>
                  <Th>État</Th>
                  <Th className="hidden md:table-cell">Réponse</Th>
                  <Th className="hidden lg:table-cell">Posée le</Th>
                </tr>
              </thead>
              <tbody>
                {visibles.map((d) => (
                  <tr key={d.reference} className="hover:bg-surface-2">
                    <Td>
                      <Link to={`/demandes/${d.reference}`} className="font-medium text-accent hover:underline">
                        {d.libelle}
                        {d.parametre ? ` (${d.parametre})` : ''}
                      </Link>
                      <span className="mt-0.5 block text-muted">{d.finalite}</span>
                    </Td>
                    <Td>
                      <BadgeStatut statut={d.statut} />
                    </Td>
                    <Td className="hidden md:table-cell">
                      <BadgeReponse reponse={d.reponse} />
                    </Td>
                    <Td className="hidden whitespace-nowrap text-muted lg:table-cell">{dateHeure(d.creee_le)}</Td>
                  </tr>
                ))}
              </tbody>
            </Tableau>
          )
        }
      </Ressource>
    </>
  )
}
