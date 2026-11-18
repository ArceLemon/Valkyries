import { Certificate, CheckCircle, Prohibit, Timer } from '@phosphor-icons/react'
import { BlocPreuve } from '../../components/BlocPreuve'
import { Ressource, Vide } from '../../components/Etats'
import { Pastille } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { dateHeure, estPasse } from '../../lib/format'
import type { Liste, PreuveCitoyen } from '../../lib/types'
import { useApi } from '../../lib/useApi'

function EtatPreuve({ p }: { p: PreuveCitoyen }) {
  if (p.revoquee) return <Pastille ton="refus" icone={Prohibit}>Révoquée</Pastille>
  if (estPasse(p.valide_jusqu_au)) return <Pastille ton="neutre" icone={Timer}>Expirée</Pastille>
  return <Pastille ton="ok" icone={CheckCircle}>Valide</Pastille>
}

export function Preuves() {
  const etat = useApi<Liste<PreuveCitoyen>>('/citoyen/preuves')

  return (
    <>
      <PageHeader
        titre="Mes preuves"
        description="Les attestations délivrées à votre sujet. Elles prouvent une réponse sans révéler vos données."
      />

      <Ressource etat={etat} lignes={3}>
        {(r) =>
          r.donnees.length === 0 ? (
            <Vide
              icone={Certificate}
              titre="Aucune attestation pour l'instant"
              texte="Quand une vérification donnera un « oui », une attestation signée apparaîtra ici."
            />
          ) : (
            <ul className="space-y-5">
              {r.donnees.map((p) => (
                <li key={p.reference} className="rounded-panel border border-border bg-surface p-5">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <h2 className="text-lg font-semibold text-text">{p.question}</h2>
                      <p className="text-base text-muted">
                        Demandée par {p.organisme}, valable jusqu'au {dateHeure(p.valide_jusqu_au)}
                      </p>
                    </div>
                    <EtatPreuve p={p} />
                  </div>
                  <details className="mt-4 border-t border-border pt-4">
                    <summary className="flex min-h-11 cursor-pointer items-center text-sm font-medium text-accent">
                      Afficher le code QR et le lien
                    </summary>
                    <div className="mt-4">
                      <BlocPreuve jeton={p.jeton} />
                    </div>
                  </details>
                </li>
              ))}
            </ul>
          )
        }
      </Ressource>
    </>
  )
}
