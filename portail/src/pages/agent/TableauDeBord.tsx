import { CalendarCheck, FileX, Gauge, Hourglass, PlusCircle, Timer, UserCheck } from '@phosphor-icons/react'
import { Link } from 'react-router-dom'
import { classesBouton } from '../../components/Bouton'
import { Ressource, Vide } from '../../components/Etats'
import { Classement, GrapheJours, GrilleKpi, Jauge, Kpi, Repartition, Tendance, duree } from '../../components/Kpi'
import { Notice } from '../../components/Notice'
import { PageHeader } from '../../components/PageHeader'
import { Panneau } from '../../components/Panneau'
import { BadgeReponse, BadgeStatut } from '../../components/Pastille'
import { RegistresCompact } from '../../components/RegistresCompact'
import { useAuth } from '../../lib/auth'
import { estEnCours, ilYa } from '../../lib/format'
import type { Demande, Liste, Registre, Statistiques } from '../../lib/types'
import { useApi } from '../../lib/useApi'

function LigneDemande({ d }: { d: Demande }) {
  return (
    <li>
      <Link
        to={`/demandes/${d.reference}`}
        className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 hover:bg-surface-2 sm:-mx-5 sm:px-5"
      >
        <div className="min-w-0">
          <p className="text-base font-medium text-text">{d.libelle}</p>
          <p className="truncate text-sm text-muted">{ilYa(d.creee_le)}</p>
        </div>
        <div className="flex items-center gap-2">
          {d.reponse && <BadgeReponse reponse={d.reponse} />}
          <BadgeStatut statut={d.statut} />
        </div>
      </Link>
    </li>
  )
}

export function TableauDeBord() {
  const { utilisateur } = useAuth()
  const stats = useApi<Statistiques>('/statistiques/organisme', () => 15000)
  const demandes = useApi<Liste<Demande>>('/demandes', (d) =>
    d?.donnees.some((x) => estEnCours(x.statut)) ? 10000 : null,
  )
  const registres = useApi<Liste<Registre>>('/registres', () => 10000)

  const liste = demandes.donnees?.donnees ?? []
  const aSuivre = liste.filter((d) => estEnCours(d.statut))
  const problemes = (registres.donnees?.donnees ?? []).filter((r) => r.etat !== 'en_service')

  return (
    <>
      <PageHeader
        titre="Tableau de bord"
        description={utilisateur?.organisme?.nom}
        actions={
          <Link to="/demandes/nouvelle" className={classesBouton('primaire')}>
            <PlusCircle size={20} aria-hidden="true" />
            Nouvelle demande
          </Link>
        }
      />

      {problemes.length > 0 && (
        <Notice ton="attention" titre="Un registre ne répond pas normalement" className="mb-6">
          Les demandes concernées attendent et reprendront seules.
        </Notice>
      )}

      <Ressource etat={stats} lignes={2}>
        {(s) => (
          <>
            <GrilleKpi>
              <Kpi
                libelle="Demandes aujourd'hui"
                valeur={s.aujourdhui}
                detail={`${s.semaine} sur 7 jours`}
                ton="bleu"
                icone={CalendarCheck}
                serie={s.serie.map((x) => x.total)}
                tendance={<Tendance actuel={s.semaine} precedent={s.semaine_precedente} />}
              />
              <Kpi
                libelle="Papiers évités"
                valeur={s.papiers_evites}
                detail="Réponses obtenues à la source"
                ton="vert"
                icone={FileX}
                serie={s.serie.map((x) => x.obtenues)}
              />
              <Kpi
                libelle="Délai médian de réponse"
                valeur={duree(s.delai_median_secondes)}
                detail="Du dépôt à la réponse"
                ton="jaune"
                icone={Timer}
              />
              <div className="flex flex-col justify-between gap-3 rounded-panel xl:flex-row xl:items-center border border-border bg-surface p-5 shadow-[0_6px_18px_rgba(10,123,162,0.07)]">
                <div>
                  <span className="grid size-11 place-items-center rounded-control bg-ok text-white">
                    <Gauge size={24} weight="bold" aria-hidden="true" />
                  </span>
                  <p className="mt-4 text-sm font-medium text-muted">Taux de réponse</p>
                  <p className="mt-0.5 text-xs text-muted">Demandes closes avec un oui ou un non</p>
                </div>
                <Jauge valeur={s.taux_reponse} ton="vert" />
              </div>
            </GrilleKpi>

            <div className="mb-6 grid gap-6 lg:grid-cols-[3fr_2fr]">
              <Panneau titre="Activité sur 14 jours">
                <GrapheJours serie={s.serie} />
              </Panneau>
              <div className="space-y-6">
                <Panneau titre="Réponses">
                  <Repartition
                    parts={[
                      { libelle: 'Oui', valeur: s.repartition.oui, ton: 'vert' },
                      { libelle: 'Non', valeur: s.repartition.non, ton: 'bleu' },
                      { libelle: 'Impossible', valeur: s.repartition.impossible, ton: 'jaune' },
                      { libelle: 'Refusées', valeur: s.repartition.refusee, ton: 'rouge' },
                      { libelle: 'Expirées', valeur: s.repartition.expiree, ton: 'gris' },
                      { libelle: 'En cours', valeur: s.repartition.en_cours, ton: 'gris' },
                    ]}
                  />
                </Panneau>
                <div className="grid grid-cols-2 gap-4">
                  <div className="rounded-panel border border-border bg-warn-soft p-4 text-warn-soft-text">
                    <UserCheck size={22} weight="bold" aria-hidden="true" />
                    <p className="mt-2 text-3xl font-bold">{s.attente_citoyen}</p>
                    <p className="text-sm font-medium">Attendent le citoyen</p>
                  </div>
                  <div className="rounded-panel border border-border bg-accent-soft p-4 text-accent-soft-text">
                    <Hourglass size={22} weight="bold" aria-hidden="true" />
                    <p className="mt-2 text-3xl font-bold">{s.attente_registre}</p>
                    <p className="text-sm font-medium">Attendent un registre</p>
                  </div>
                </div>
              </div>
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
              <Panneau titre="Questions les plus posées">
                <Classement lignes={s.top_questions.map((q) => ({ libelle: q.libelle, valeur: q.demandes }))} />
              </Panneau>
              <Panneau titre="Registres">
                <Ressource etat={registres} lignes={1}>
                  {(r) => <RegistresCompact registres={r.donnees} />}
                </Ressource>
              </Panneau>
            </div>
          </>
        )}
      </Ressource>

      <Panneau
        titre={aSuivre.length > 0 ? `À suivre (${aSuivre.length})` : 'Dernières demandes'}
        actions={
          <Link to="/demandes" className="text-sm font-medium text-accent hover:underline">
            Tout voir
          </Link>
        }
      >
        <Ressource etat={demandes} lignes={3}>
          {() =>
            liste.length === 0 ? (
              <Vide
                titre="Aucune demande pour l'instant"
                texte="Posez votre première question : vous recevrez un oui ou un non, sans papier."
                action={
                  <Link to="/demandes/nouvelle" className={classesBouton('primaire')}>
                    Poser une question
                  </Link>
                }
              />
            ) : (
              <ul className="divide-y divide-border">
                {(aSuivre.length > 0 ? aSuivre : liste).slice(0, 5).map((d) => (
                  <LigneDemande key={d.reference} d={d} />
                ))}
              </ul>
            )
          }
        </Ressource>
      </Panneau>
    </>
  )
}
