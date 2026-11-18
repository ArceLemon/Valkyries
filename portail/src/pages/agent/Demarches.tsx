import { XCircle } from '@phosphor-icons/react'
import { useMemo, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { Bouton } from '../../components/Bouton'
import { Input } from '../../components/Champs'
import { Ressource } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Panneau } from '../../components/Panneau'
import { BadgeReponse, BadgeStatut, Pastille } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { api, messageErreur } from '../../lib/api'
import type { Demarche, Liste, ResultatDemarche } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function Demarches() {
  const etat = useApi<Liste<Demarche>>('/demarches')
  const [code, setCode] = useState('')
  const [identifiant, setIdentifiant] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [erreurIdentifiant, setErreurIdentifiant] = useState<string | undefined>()
  const [envoi, setEnvoi] = useState(false)
  const [resultat, setResultat] = useState<ResultatDemarche | null>(null)
  const refResultat = useRef<HTMLDivElement>(null)

  const demarches = useMemo(() => etat.donnees?.donnees ?? [], [etat.donnees])
  const active = demarches.find((d) => d.code === code) ?? demarches[0] ?? null

  async function lancer(e: FormEvent) {
    e.preventDefault()
    if (!active) return
    if (!identifiant.trim()) {
      setErreurIdentifiant("Indiquez l'identifiant du citoyen.")
      return
    }
    setErreurIdentifiant(undefined)
    setErreur(null)
    setResultat(null)
    setEnvoi(true)
    try {
      const r = await api<ResultatDemarche>(`/demarches/${encodeURIComponent(active.code)}/lancer`, {
        method: 'POST',
        corps: {
          citoyen: { identifiant: identifiant.trim() },
        },
      })
      setResultat(r)
      requestAnimationFrame(() => refResultat.current?.focus())
    } catch (err) {
      setErreur(messageErreur(err))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <PageHeader
        titre="Démarches"
        description="Lancez en une fois toutes les vérifications d'une démarche : le citoyen n'a rien à apporter."
      />

      <Ressource etat={etat} lignes={3}>
        {() => (
          <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
            <form onSubmit={lancer} noValidate className="space-y-6">
              <Panneau titre="Choisissez la démarche">
                <fieldset>
                  <legend className="sr-only">Démarche</legend>
                  <div className="space-y-3">
                    {demarches.map((d) => (
                      <label
                        key={d.code}
                        className="flex cursor-pointer gap-3 rounded-control border border-border-strong p-4 has-[:checked]:border-accent has-[:checked]:bg-accent-soft"
                      >
                        <input
                          type="radio"
                          name="demarche"
                          value={d.code}
                          checked={active?.code === d.code}
                          onChange={() => setCode(d.code)}
                          className="mt-1 size-5 shrink-0 accent-accent"
                        />
                        <span className="min-w-0">
                          <span className="block text-base font-semibold text-text">{d.libelle}</span>
                          <span className="block text-sm text-muted">{d.finalite}</span>
                          <span className="mt-2 flex flex-wrap gap-2">
                            {d.questions.map((q) => (
                              <span key={q.question} className="rounded-control border border-border bg-surface px-2 py-0.5 text-sm text-text">
                                {q.libelle}
                              </span>
                            ))}
                          </span>
                        </span>
                      </label>
                    ))}
                  </div>
                </fieldset>
              </Panneau>

              <Panneau titre="Le citoyen">
                <div className="space-y-5">
                  <Input
                    libelle="Identifiant du citoyen"
                    aide="L'identifiant unique suffit : aucune autre donnée n'est demandée. Par exemple BF-0001 dans la démonstration."
                    autoComplete="off"
                    value={identifiant}
                    onChange={(e) => setIdentifiant(e.target.value)}
                    erreur={erreurIdentifiant}
                    required
                  />
                </div>
              </Panneau>

              {erreur && <Notice ton="erreur">{erreur}</Notice>}

              <Bouton type="submit" chargement={envoi} disabled={!active}>
                Lancer la démarche
              </Bouton>
            </form>

            <div ref={refResultat} tabIndex={-1} className="outline-none" aria-live="polite">
              {resultat ? (
                <Panneau titre="Résultat">
                  <p className="text-5xl font-semibold text-accent">
                    {resultat.pieces_evitees}
                    <span className="text-2xl text-muted"> sur {resultat.demandes.length}</span>
                  </p>
                  <p className="mt-2 text-base text-muted">
                    réponses obtenues tout de suite, sans qu'aucun papier soit demandé au citoyen.
                  </p>

                  <ul className="mt-6 divide-y divide-border">
                    {resultat.demandes.map((d) => (
                      <li key={d.question} className="py-3">
                        <p className="text-base font-medium text-text">{d.libelle ?? d.question}</p>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                          {d.statut === 'rejetee' ? (
                            <Pastille ton="refus" icone={XCircle}>Refusée</Pastille>
                          ) : (
                            <BadgeStatut statut={d.statut} />
                          )}
                          {d.reponse && <BadgeReponse reponse={d.reponse} />}
                          {d.reference && (
                            <Link to={`/demandes/${d.reference}`} className="text-sm font-medium text-accent hover:underline">
                              Voir le détail
                            </Link>
                          )}
                        </div>
                        {d.erreur && <p className="mt-2 text-sm text-danger-soft-text">{d.erreur}</p>}
                      </li>
                    ))}
                  </ul>
                </Panneau>
              ) : (
                <Panneau titre="Comment ça marche">
                  <ol className="space-y-3 text-base text-text">
                    <li>Chaque question de la démarche part en même temps.</li>
                    <li>Celles qui n'exigent pas d'accord répondent tout de suite.</li>
                    <li>Les questions sensibles attendent la décision du citoyen : vous n'avez rien à relancer.</li>
                  </ol>
                </Panneau>
              )}
            </div>
          </div>
        )}
      </Ressource>
    </>
  )
}
