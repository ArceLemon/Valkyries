import { useMemo, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Bouton, classesBouton } from '../../components/Bouton'
import { Input, Select } from '../../components/Champs'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Panneau } from '../../components/Panneau'
import { BadgeRegime, BadgeSensibilite } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { ApiError, api, messageErreur } from '../../lib/api'
import { useAuth } from '../../lib/auth'
import type { Demande, DroitDisponible, Liste } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function NouvelleDemande() {
  const { utilisateur } = useAuth()
  const navigate = useNavigate()
  const droits = useApi<Liste<DroitDisponible>>('/droits-disponibles')

  const [finalite, setFinalite] = useState('')
  const [question, setQuestion] = useState('')
  const [parametre, setParametre] = useState('')
  const [identifiant, setIdentifiant] = useState('')
  const [problemes, setProblemes] = useState<string[]>([])
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const refResume = useRef<HTMLDivElement>(null)

  const liste = useMemo(() => droits.donnees?.donnees ?? [], [droits.donnees])
  const finalites = useMemo(() => [...new Set(liste.map((d) => d.finalite))], [liste])
  const finaliteActive = finalites.includes(finalite) ? finalite : (finalites[0] ?? '')
  const questions = liste.filter((d) => d.finalite === finaliteActive)
  const choisie = questions.find((q) => q.question === question) ?? questions[0] ?? null

  function valider(): Record<string, string> {
    const e: Record<string, string> = {}
    if (!identifiant.trim()) e.identifiant = "Indiquez l'identifiant du citoyen."
    if (choisie?.parametree && !parametre.trim()) e.parametre = 'Cette question demande un paramètre.'
    return e
  }

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    if (!choisie) return

    const trouvees = valider()
    setErreurs(trouvees)
    if (Object.keys(trouvees).length > 0) {
      setProblemes(Object.values(trouvees))
      requestAnimationFrame(() => refResume.current?.focus())
      return
    }

    setProblemes([])
    setEnvoi(true)
    try {
      const demande = await api<Demande>('/demandes', {
        method: 'POST',
        corps: {
          question: choisie.question,
          parametre: choisie.parametree ? parametre.trim() : undefined,
          finalite: finaliteActive,
          citoyen: { identifiant: identifiant.trim() },
        },
      })
      navigate(`/demandes/${demande.reference}`)
    } catch (err) {
      const messages =
        err instanceof ApiError && Object.keys(err.erreurs).length > 0
          ? Object.values(err.erreurs).flat()
          : [messageErreur(err)]
      setProblemes(messages)
      setEnvoi(false)
      requestAnimationFrame(() => refResume.current?.focus())
    }
  }

  return (
    <>
      <PageHeader
        titre="Nouvelle demande"
        description="Posez une question sur un citoyen. Vous recevrez un oui ou un non, jamais la donnée elle-même."
      />

      <Ressource etat={droits} lignes={3}>
        {() =>
          liste.length === 0 ? (
            <Vide
              titre="Aucune question disponible pour votre organisme"
              texte={
                utilisateur?.role === 'responsable'
                  ? "Aucun droit actif. Demandez un accès à la source depuis la page Droits : elle devra le valider."
                  : "Votre organisme n'a pas encore de droit actif. Le responsable de l'organisme peut en demander à la source."
              }
              action={
                utilisateur?.role === 'responsable' ? (
                  <Link to="/droits" className={classesBouton('primaire')}>
                    Aller aux droits
                  </Link>
                ) : undefined
              }
            />
          ) : (
            <form onSubmit={soumettre} noValidate className="grid gap-8 lg:grid-cols-[3fr_2fr]">
              <div className="space-y-6">
                {problemes.length > 0 && (
                  <Notice ton="erreur" titre="Cette demande ne peut pas être envoyée" ref={refResume}>
                    <ul className="list-disc space-y-1 pl-5">
                      {problemes.map((p) => (
                        <li key={p}>{p}</li>
                      ))}
                    </ul>
                  </Notice>
                )}

                <Panneau titre="La question">
                  <div className="space-y-5">
                    <Select
                      libelle="Pour quelle finalité ?"
                      aide="La finalité doit correspondre à celle de votre habilitation."
                      value={finaliteActive}
                      onChange={(e) => setFinalite(e.target.value)}
                    >
                      {finalites.map((f) => (
                        <option key={f} value={f}>
                          {f}
                        </option>
                      ))}
                    </Select>
                    <Select
                      libelle="Quelle question ?"
                      value={choisie?.question ?? ''}
                      onChange={(e) => setQuestion(e.target.value)}
                    >
                      {questions.map((q) => (
                        <option key={q.question} value={q.question}>
                          {q.libelle}
                        </option>
                      ))}
                    </Select>
                    {choisie?.parametree && (
                      <Input
                        libelle="Paramètre"
                        aide="Par exemple l'âge minimum ou le code du pays."
                        value={parametre}
                        onChange={(e) => setParametre(e.target.value)}
                        erreur={erreurs.parametre}
                        required
                      />
                    )}
                  </div>
                </Panneau>

                <Panneau titre="Le citoyen">
                  <div className="space-y-5">
                    <Input
                      libelle="Identifiant du citoyen"
                      aide="L'identifiant unique suffit : aucune autre donnée n'est demandée. Par exemple BF-0001 dans la démonstration."
                      autoComplete="off"
                      value={identifiant}
                      onChange={(e) => setIdentifiant(e.target.value)}
                      erreur={erreurs.identifiant}
                      required
                    />
                  </div>
                </Panneau>

                <div className="flex flex-wrap gap-3">
                  <Bouton type="submit" chargement={envoi}>
                    Envoyer la demande
                  </Bouton>
                  <Link to="/demandes" className={classesBouton('secondaire')}>
                    Annuler
                  </Link>
                </div>
              </div>

              {choisie && (
                <aside aria-label="Ce qui va se passer">
                  <Panneau titre="Ce qui va se passer" className="lg:sticky lg:top-6">
                    <div className="mb-4 flex flex-wrap gap-2">
                      <BadgeRegime regime={choisie.regime} />
                      <BadgeSensibilite sensibilite={choisie.sensibilite} />
                    </div>
                    <ol className="space-y-4 text-base text-text">
                      <li>
                        Votre organisme demande : <strong className="font-semibold">{choisie.libelle}</strong>.
                      </li>
                      <li>
                        {choisie.regime === 'consentement'
                          ? "Le citoyen reçoit la demande et décide. Le registre n'est interrogé qu'après son accord."
                          : "Le registre est interrogé tout de suite. Le citoyen en est informé et le voit dans son historique."}
                      </li>
                      <li>Vous recevez seulement oui, non ou impossible. Aucune donnée du registre ne vous est transmise.</li>
                      {choisie.sensibilite === 'elevee' && (
                        <li>Question sensible : l'attestation éventuelle reste valable peu de temps.</li>
                      )}
                    </ol>
                    {choisie.description && <p className="mt-4 border-t border-border pt-4 text-sm text-muted">{choisie.description}</p>}
                  </Panneau>
                </aside>
              )}
            </form>
          )
        }
      </Ressource>
    </>
  )
}
