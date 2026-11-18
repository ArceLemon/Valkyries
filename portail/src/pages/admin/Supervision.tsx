import { Buildings, CalendarCheck, Database, Gauge, SealCheck, ShieldCheck, Timer, UsersThree } from '@phosphor-icons/react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Bouton } from '../../components/Bouton'
import { Ressource } from '../../components/Etats'
import { Classement, GrapheJours, GrilleKpi, Jauge, Kpi, Repartition, Tendance, duree } from '../../components/Kpi'
import { Notice } from '../../components/Notice'
import { PageHeader } from '../../components/PageHeader'
import { Panneau } from '../../components/Panneau'
import { RegistresCompact } from '../../components/RegistresCompact'
import { api, messageErreur } from '../../lib/api'
import { ilYa, libelleAction, libelleRegistre } from '../../lib/format'
import { MOTIFS_RUPTURE } from '../../lib/rupture'
import type { LigneJournal, Liste, Registre, StatistiquesPlateforme, VerificationJournal } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function Supervision() {
  const stats = useApi<StatistiquesPlateforme>('/statistiques/plateforme', () => 15000)
  const registres = useApi<Liste<Registre>>('/registres', () => 10000)
  const journal = useApi<Liste<LigneJournal>>('/journal', () => 15000)
  const [verification, setVerification] = useState<VerificationJournal | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function verifier() {
    setEnvoi(true)
    setErreur(null)
    try {
      setVerification(await api<VerificationJournal>('/journal/verifier-chaine', { method: 'POST' }))
    } catch (e) {
      setErreur(messageErreur(e))
    } finally {
      setEnvoi(false)
    }
  }

  const listeRegistres = registres.donnees?.donnees ?? []
  const enService = listeRegistres.filter((r) => r.etat === 'en_service').length

  return (
    <>
      <PageHeader titre="Supervision" description="Aucune donnée de citoyen n'est visible ici." />

      <Ressource etat={stats} lignes={2}>
        {(s) => (
          <>
            <GrilleKpi>
              <Kpi
                libelle="Demandes sur 7 jours"
                valeur={s.semaine}
                detail={`${s.aujourdhui} aujourd'hui`}
                ton="bleu"
                icone={CalendarCheck}
                serie={s.serie.map((x) => x.total)}
                tendance={<Tendance actuel={s.semaine} precedent={s.semaine_precedente} />}
              />
              <Kpi
                libelle="Registres en service"
                valeur={registres.donnees ? `${enService}/${listeRegistres.length}` : '-'}
                detail={enService === listeRegistres.length ? 'Tout répond normalement' : 'Au moins un registre dégradé'}
                ton={enService === listeRegistres.length ? 'vert' : 'rouge'}
                icone={Database}
              />
              <Kpi
                libelle="Délai médian de réponse"
                valeur={duree(s.delai_median_secondes)}
                detail="Toutes demandes confondues"
                ton="jaune"
                icone={Timer}
              />
              <div className="flex flex-col justify-between gap-3 rounded-panel xl:flex-row xl:items-center border border-border bg-surface p-5 shadow-[0_6px_18px_rgba(10,123,162,0.07)]">
                <div>
                  <span className="grid size-11 place-items-center rounded-control bg-ok text-white">
                    <Gauge size={24} weight="bold" aria-hidden="true" />
                  </span>
                  <p className="mt-4 text-sm font-medium text-muted">Taux de réponse</p>
                  <p className="mt-0.5 text-xs text-muted">Oui ou non obtenu</p>
                </div>
                <Jauge valeur={s.taux_reponse} ton="vert" />
              </div>
            </GrilleKpi>

            <GrilleKpi>
              <Kpi libelle="Organismes actifs" valeur={s.organismes_actifs} unite={`/ ${s.organismes_total}`} ton="bleu" icone={Buildings} />
              <Kpi libelle="Comptes d'administration" valeur={s.comptes_actifs} detail="Actifs" ton="jaune" icone={UsersThree} />
              <Kpi
                libelle="Attestations valides"
                valeur={s.attestations.valides}
                detail={`${s.attestations.emises} émises, ${s.attestations.revoquees} révoquées`}
                ton="vert"
                icone={SealCheck}
              />
              <Kpi
                libelle="Lignes du journal"
                valeur={s.journal.lignes}
                detail={`${s.journal.points_de_controle} points de contrôle signés`}
                ton="rouge"
                icone={ShieldCheck}
              />
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
                <Panneau titre="Consentements">
                  <Repartition
                    parts={[
                      { libelle: 'Accordés', valeur: s.consentements.accepte, ton: 'vert' },
                      { libelle: 'En attente', valeur: s.consentements.en_attente, ton: 'jaune' },
                      { libelle: 'Refusés', valeur: s.consentements.refuse, ton: 'rouge' },
                      { libelle: 'Retirés', valeur: s.consentements.retire, ton: 'gris' },
                    ]}
                  />
                </Panneau>
              </div>
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
              <Panneau titre="Demandes par registre">
                <Classement lignes={s.par_registre.map((r) => ({ libelle: libelleRegistre(r.registre), valeur: r.demandes }))} />
              </Panneau>
              <Panneau titre="Questions les plus posées">
                <Classement lignes={s.top_questions.map((q) => ({ libelle: q.libelle, valeur: q.demandes }))} />
              </Panneau>
            </div>
          </>
        )}
      </Ressource>

      <div className="grid gap-6 lg:grid-cols-2">
        <Panneau titre="Registres en direct">
          <Ressource etat={registres} lignes={1}>
            {(r) => <RegistresCompact registres={r.donnees} />}
          </Ressource>
        </Panneau>

        <Panneau
          titre="Intégrité du journal"
          actions={
            <Link to="/journal" className="text-sm font-medium text-accent hover:underline">
              Ouvrir le journal
            </Link>
          }
        >
          <div className="space-y-4">
            <Bouton onClick={() => void verifier()} chargement={envoi} icone={<ShieldCheck size={20} />}>
              Vérifier maintenant
            </Bouton>
            <div aria-live="polite">
              {erreur && <Notice ton="erreur">{erreur}</Notice>}
              {verification &&
                (verification.valide ? (
                  <Notice ton="succes" titre="Journal intègre">
                    {verification.total} ligne(s) vérifiée(s)
                    {verification.points_de_controle !== undefined &&
                      `, ${verification.points_de_controle} point(s) de contrôle signé(s)`}
                    .
                  </Notice>
                ) : (
                  <Notice ton="erreur" titre="Le journal a été altéré">
                    Ligne {verification.rupture?.id} :{' '}
                    {MOTIFS_RUPTURE[verification.rupture?.motif ?? ''] ?? verification.rupture?.motif}.
                  </Notice>
                ))}
            </div>
          </div>
        </Panneau>
      </div>

      <div className="mt-6">
        <Panneau
          titre="Derniers événements"
          actions={
            <Link to="/journal" className="text-sm font-medium text-accent hover:underline">
              Tout voir
            </Link>
          }
        >
          <Ressource etat={journal} lignes={3}>
            {(j) => (
              <ul className="divide-y divide-border">
                {j.donnees.slice(0, 5).map((l) => (
                  <li key={l.id} className="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0">
                    <span className="text-base text-text">{libelleAction(l.action)}</span>
                    <span className="text-sm text-muted">{ilYa(l.date)}</span>
                  </li>
                ))}
              </ul>
            )}
          </Ressource>
        </Panneau>
      </div>
    </>
  )
}
