import { Link } from 'react-router-dom'
import { classesBouton } from '../../components/Bouton'
import { Ressource, Vide } from '../../components/Etats'
import { ListeRegistres } from '../../components/ListeRegistres'
import { Panneau } from '../../components/Panneau'
import { BadgeRegime, BadgeSensibilite } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { useAuth } from '../../lib/auth'
import { dureeValidite, libelleRegistre } from '../../lib/format'
import type { AccesRegistre, Liste, QuestionCatalogue, Registre } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function MonRegistre() {
  const { utilisateur } = useAuth()
  const detenus = utilisateur?.organisme?.registres ?? []
  const registres = useApi<Liste<Registre>>('/registres', () => 10000)
  const attente = useApi<Liste<AccesRegistre>>('/registre/acces')
  const catalogue = useApi<Liste<QuestionCatalogue>>('/catalogue')

  const nombre = attente.donnees?.donnees.length ?? 0
  const questions = (catalogue.donnees?.donnees ?? []).filter((q) => detenus.includes(q.registre))

  return (
    <>
      <PageHeader
        titre="Mon registre"
        description={`${utilisateur?.organisme?.nom ?? ''}. Vous décidez qui peut vous interroger, et sur quelles questions.`}
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <Panneau titre="État en direct" description="Mis à jour toutes les dix secondes.">
          <Ressource etat={registres} lignes={2}>
            {(r) => {
              const miens = r.donnees.filter((x) => detenus.includes(x.registre))
              return miens.length === 0 ? (
                <p className="text-base text-muted">Aucun registre n'est rattaché à votre organisme.</p>
              ) : (
                <ListeRegistres registres={miens} />
              )
            }}
          </Ressource>
        </Panneau>

        <Panneau titre="Accès à valider">
          <Ressource etat={attente} lignes={1}>
            {() => (
              <div className="space-y-4">
                <p className="text-3xl font-semibold text-text">{nombre}</p>
                <p className="text-base text-muted">
                  {nombre === 0
                    ? "Aucune administration n'attend votre décision."
                    : nombre === 1
                      ? 'Une administration attend votre décision.'
                      : `${nombre} administrations attendent votre décision.`}
                </p>
                <Link to="/registre/acces" className={classesBouton(nombre > 0 ? 'primaire' : 'secondaire', 's')}>
                  {nombre > 0 ? 'Examiner les demandes' : 'Voir les accès accordés'}
                </Link>
              </div>
            )}
          </Ressource>
        </Panneau>
      </div>

      <div className="mt-6">
        <Panneau titre="Questions auxquelles votre registre répond" description="Les administrations ne reçoivent jamais la fiche : seulement un oui, un non ou impossible.">
          <Ressource etat={catalogue} lignes={2}>
            {() =>
              questions.length === 0 ? (
                <Vide titre="Aucune question publiée" />
              ) : (
                <ul className="divide-y divide-border">
                  {questions.map((q) => (
                    <li key={q.code} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                      <div>
                        <p className="text-base font-medium text-text">{q.libelle}</p>
                        <p className="text-sm text-muted">
                          {libelleRegistre(q.registre)}, preuve valable {dureeValidite(q.validite_minutes)}
                        </p>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <BadgeRegime regime={q.regime} />
                        <BadgeSensibilite sensibilite={q.sensibilite} />
                      </div>
                    </li>
                  ))}
                </ul>
              )
            }
          </Ressource>
        </Panneau>
      </div>
    </>
  )
}
