import { BellRinging } from '@phosphor-icons/react'
import { Link } from 'react-router-dom'
import { classesBouton } from '../../components/Bouton'
import { EvenementCitoyen } from '../../components/EvenementCitoyen'
import { Ressource, Vide } from '../../components/Etats'
import { Panneau } from '../../components/Panneau'
import { PageHeader } from '../../components/PageHeader'
import type { ConsentementACiter, ConsultationCitoyen, Liste } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function Accueil() {
  const aValider = useApi<Liste<ConsentementACiter>>('/citoyen/demandes-a-valider', () => 15000)
  const historique = useApi<Liste<ConsultationCitoyen>>('/citoyen/historique')
  const nombre = aValider.donnees?.donnees.length ?? 0

  return (
    <>
      <PageHeader titre="Mon espace" description="Vos données, vos décisions. Vous voyez ici tout ce qui vous concerne." />

      <div className="space-y-6">
        {nombre > 0 && (
          <section
            aria-label="Demandes à valider"
            className="flex flex-col gap-4 rounded-panel border border-border bg-warn-soft p-5 text-warn-soft-text sm:flex-row sm:items-center sm:justify-between"
          >
            <div className="flex items-start gap-3">
              <BellRinging size={28} className="mt-0.5 shrink-0" aria-hidden="true" />
              <div>
                <h2 className="text-lg font-semibold">
                  {nombre === 1 ? 'Une demande attend votre décision' : `${nombre} demandes attendent votre décision`}
                </h2>
                <p className="text-base">Rien n'est consulté tant que vous n'avez pas répondu.</p>
              </div>
            </div>
            <Link to="/mon-espace/a-valider" className={classesBouton('primaire')}>
              Voir les demandes
            </Link>
          </section>
        )}

        <Panneau
          titre="Dernières consultations de vos données"
          actions={
            <Link to="/mon-espace/historique" className="text-sm font-medium text-accent hover:underline">
              Tout voir
            </Link>
          }
        >
          <Ressource etat={historique} lignes={3}>
            {(h) =>
              h.donnees.length === 0 ? (
                <Vide
                  titre="Personne n'a consulté vos données"
                  texte="Dès qu'une administration vérifiera une information vous concernant, vous la verrez ici, avec la question posée et la réponse donnée."
                />
              ) : (
                <ul className="divide-y divide-border">
                  {h.donnees.slice(0, 5).map((e, i) => (
                    <EvenementCitoyen key={`${e.date}-${i}`} e={e} />
                  ))}
                </ul>
              )
            }
          </Ressource>
        </Panneau>

        <Panneau titre="Ce que ce système garantit">
          <ul className="space-y-3 text-base text-text">
            <li>Une administration reçoit seulement un oui ou un non, jamais votre fiche.</li>
            <li>Pour les questions sensibles, comme le casier, rien n'est demandé sans votre accord.</li>
            <li>Chaque consultation est écrite dans un journal impossible à modifier en secret.</li>
          </ul>
        </Panneau>
      </div>
    </>
  )
}
