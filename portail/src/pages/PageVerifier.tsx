import { CheckCircle, Prohibit, Question, Timer, XCircle } from '@phosphor-icons/react'
import type { Icon } from '@phosphor-icons/react'
import { useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Bouton } from '../components/Bouton'
import { Textarea } from '../components/Champs'
import { Notice } from '../components/Notice'
import { Dl, Panneau } from '../components/Panneau'
import { PageHeader } from '../components/PageHeader'
import { api, messageErreur } from '../lib/api'
import { cx } from '../lib/cx'
import { dateHeure, libelleRegistre } from '../lib/format'
import type { ResultatVerification } from '../lib/types'

const ISSUES: Record<
  ResultatVerification['etat'],
  { titre: string; texte: string; icone: Icon; classes: string }
> = {
  valide: {
    titre: 'Attestation valide',
    texte: "La signature est authentique et l'attestation n'a pas expiré.",
    icone: CheckCircle,
    classes: 'border-border bg-accent-soft text-accent-soft-text',
  },
  expiree: {
    titre: 'Attestation expirée',
    texte: 'Elle était authentique, mais sa durée de validité est dépassée. Demandez-en une nouvelle.',
    icone: Timer,
    classes: 'border-border bg-surface-2 text-text',
  },
  alteree: {
    titre: 'Attestation modifiée ou illisible',
    texte: 'La signature ne correspond pas au contenu : cette attestation ne doit pas être acceptée.',
    icone: XCircle,
    classes: 'border-danger bg-danger-soft text-danger-soft-text',
  },
  revoquee: {
    titre: 'Attestation révoquée',
    texte: "Le citoyen a retiré son consentement : cette attestation n'est plus valable.",
    icone: Prohibit,
    classes: 'border-danger bg-danger-soft text-danger-soft-text',
  },
  cle_inconnue: {
    titre: 'Clé de signature inconnue',
    texte: "Cette attestation n'a pas été signée par une clé que ce système connaît.",
    icone: Question,
    classes: 'border-border bg-warn-soft text-warn-soft-text',
  },
}

export function PageVerifier() {
  const [params] = useSearchParams()
  const [jeton, setJeton] = useState(params.get('jeton') ?? '')
  const [resultat, setResultat] = useState<ResultatVerification | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const refResultat = useRef<HTMLDivElement>(null)
  const dejaLance = useRef(false)

  async function verifier(valeur: string) {
    setErreur(null)
    setResultat(null)
    setEnvoi(true)
    try {
      const r = await api<ResultatVerification>('/preuves/verifier', {
        method: 'POST',
        corps: { jeton: valeur.trim() },
      })
      setResultat(r)
      requestAnimationFrame(() => refResultat.current?.focus())
    } catch (e) {
      setErreur(messageErreur(e))
    } finally {
      setEnvoi(false)
    }
  }

  // Lien ou code QR : la vérification démarre toute seule.
  useEffect(() => {
    const initial = params.get('jeton')
    if (initial && !dejaLance.current) {
      dejaLance.current = true
      void verifier(initial)
    }
  }, [params])

  function soumettre(e: FormEvent) {
    e.preventDefault()
    if (jeton.trim()) void verifier(jeton)
  }

  const issue = resultat ? ISSUES[resultat.etat] : null
  const a = resultat?.attestation ?? null

  return (
    <>
      <PageHeader
        titre="Vérifier une attestation"
        description="Collez le contenu d'une attestation, ou ouvrez son lien. Aucun compte n'est nécessaire."
      />

      <div className="grid gap-8 lg:grid-cols-[1fr_1fr]">
        <form onSubmit={soumettre} className="space-y-4" noValidate>
          <Textarea
            libelle="Attestation"
            aide="Le texte commence par des lettres et des chiffres, avec un point au milieu."
            rows={7}
            spellCheck={false}
            autoComplete="off"
            className="mono"
            value={jeton}
            onChange={(e) => setJeton(e.target.value)}
          />
          <Bouton type="submit" chargement={envoi} disabled={!jeton.trim()}>
            Vérifier
          </Bouton>
        </form>

        <div aria-live="polite">
          {erreur && <Notice ton="erreur">{erreur}</Notice>}

          {issue && (
            <div ref={refResultat} tabIndex={-1} className="space-y-5 outline-none">
              <div className={cx('flex gap-4 rounded-panel border p-5', issue.classes)}>
                <issue.icone size={32} className="shrink-0" aria-hidden="true" />
                <div>
                  <h2 className="text-xl font-semibold">{issue.titre}</h2>
                  <p className="mt-1 text-base">{issue.texte}</p>
                </div>
              </div>

              {a && (
                <Panneau titre="Ce que l'attestation affirme">
                  <Dl
                    elements={[
                      ['Question', a.parametre ? `${a.question} (${a.parametre})` : a.question],
                      ['Réponse', <strong key="r" className="font-semibold">{a.reponse === 'oui' ? 'Oui' : a.reponse}</strong>],
                      ['Demandée par', a.demandeur],
                      ['Source', libelleRegistre(a.source)],
                      ['Émise le', dateHeure(a.emise_le)],
                      ['Valable jusqu\'au', dateHeure(a.valide_jusqu_au)],
                      ['Clé de signature', <span key="k" className="mono">{a.kid}</span>],
                    ]}
                  />
                  <p className="mt-4 text-sm text-muted">
                    Aucune donnée personnelle du citoyen n'apparaît ici : seulement ce que le registre a confirmé.
                  </p>
                </Panneau>
              )}
            </div>
          )}
        </div>
      </div>
    </>
  )
}
