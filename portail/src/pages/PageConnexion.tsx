import {
  BookOpenText,
  ClockCounterClockwise,
  Eye,
  EyeSlash,
  Key,
  Scales,
  SealCheck,
  ShieldCheck,
  UsersThree,
} from '@phosphor-icons/react'
import symbole from '../assets/kumabana-mark.png'
import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { Bouton } from '../components/Bouton'
import { BoutonTheme } from '../components/BoutonTheme'
import { Input } from '../components/Champs'
import { Marque } from '../components/Marque'
import { Notice } from '../components/Notice'
import { messageErreur } from '../lib/api'
import { useAuth } from '../lib/auth'
import { accueilDuRole } from '../lib/nav'
import { useTitre } from '../lib/useTitre'

const MOT_DE_PASSE_DEMO = 'kumabana-demo'

const ORBITE = [
  { Icone: UsersThree, nom: 'Citoyens et agents' },
  { Icone: ShieldCheck, nom: 'Consentement' },
  { Icone: Scales, nom: 'Droits' },
  { Icone: Key, nom: "Clés d'API" },
  { Icone: ClockCounterClockwise, nom: 'Journal' },
  { Icone: SealCheck, nom: 'Attestations signées' },
]

const COMPTES_DEMO: { role: string; email: string }[] = [
  { role: 'Agent du Registre du commerce', email: 'agent.commerce@demo.kumabana.test' },
  { role: 'Responsable du Registre du commerce', email: 'responsable.commerce@demo.kumabana.test' },
  { role: 'Agent de la Mairie', email: 'agent.mairie@demo.kumabana.test' },
  { role: "Gestionnaire de l'état civil", email: 'gestionnaire.etat-civil@demo.kumabana.test' },
  { role: 'Gestionnaire de la nationalité', email: 'gestionnaire.nationalite@demo.kumabana.test' },
  { role: 'Gestionnaire du casier', email: 'gestionnaire.casier@demo.kumabana.test' },
  { role: 'Citoyen : Ousmane (BF-0001)', email: 'ousmane@demo.kumabana.test' },
  { role: 'Citoyenne : Aminata (BF-0003)', email: 'aminata@demo.kumabana.test' },
  { role: 'Citoyenne : Fatimata (BF-0005)', email: 'fatimata@demo.kumabana.test' },
  { role: 'Administrateur de la plateforme', email: 'admin@demo.kumabana.test' },
  { role: 'Auditeur', email: 'auditeur@demo.kumabana.test' },
]

export function PageConnexion() {
  useTitre('Connexion')
  const { utilisateur, connexion } = useAuth()
  const navigate = useNavigate()
  const lieu = useLocation()
  const [email, setEmail] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [afficher, setAfficher] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const refErreur = useRef<HTMLDivElement>(null)

  if (utilisateur) return <Navigate to={accueilDuRole(utilisateur.role)} replace />

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    setErreur(null)
    setEnvoi(true)
    try {
      const u = await connexion(email.trim(), motDePasse)
      const depuis = (lieu.state as { depuis?: string } | null)?.depuis
      navigate(depuis ?? accueilDuRole(u.role), { replace: true })
    } catch (err) {
      setErreur(messageErreur(err))
      setEnvoi(false)
      // Le message d'erreur reçoit le focus pour être annoncé tout de suite.
      requestAnimationFrame(() => refErreur.current?.focus())
    }
  }

  return (
    <div className="grid min-h-dvh lg:grid-cols-[1fr_34rem]">
      <div className="bande-drapeau fixed inset-x-0 top-0 z-10" aria-hidden="true" />
      <aside className="fond-marque relative hidden flex-col justify-between overflow-hidden p-12 text-white lg:flex">
        {/* Décor : le jaune et le rouge de la carte, et des ondes qui rappellent le croissant du logo */}
        <div aria-hidden="true">
          <span className="absolute -right-32 bottom-0 size-[30rem] rounded-full bg-[#7dd6f0] opacity-30 blur-3xl" />
          <span className="absolute -top-44 -right-44 size-[30rem] rounded-full border-[5px] border-[#fcc304]/85" />
          <span className="absolute -top-20 -right-20 size-[18rem] rounded-full border-[3px] border-white/20" />
          <span className="absolute -bottom-64 -left-56 size-[30rem] rounded-full border-[5px] border-[#eb1525]/80" />
          <span className="absolute top-1/3 -right-40 size-[28rem] rounded-full border-2 border-white/15" />
        </div>

        <div />
        <div className="relative">
          <div className="relative grid size-80 place-items-center">
            {/* Ce que fait la plateforme, en orbite autour du logo */}
            <div className="orbite" aria-hidden="true">
              <span className="orbite-trace" />
              {ORBITE.map(({ Icone, nom }, i) => (
                <span key={nom} className="orbite-pos" style={{ ['--a' as string]: `${i * 60}deg` }}>
                  <span className="orbite-puce" title={nom}>
                    <Icone size={22} weight="bold" aria-hidden="true" />
                  </span>
                </span>
              ))}
            </div>
            <div className="relative grid size-44 place-items-center rounded-full bg-white shadow-[0_0_0_12px_rgba(255,255,255,0.18),0_24px_60px_rgba(2,62,88,0.4)]">
              <img
                src={symbole}
                alt="Logo KUMA BANA"
                width={128}
                height={128}
                className="size-32 object-contain"
              />
            </div>
          </div>
          <h2 className="mt-14 max-w-[22ch] text-4xl font-bold text-white">
            Posez une question, recevez un oui ou un non
          </h2>
          <p className="mt-3 max-w-[52ch] text-lg text-white/90">
            Les administrations vérifient une information à la source, sans demander de papier au
            citoyen. Le citoyen voit toujours qui a consulté ses données.
          </p>
        </div>
        <div />

      </aside>

      <main id="contenu" className="flex flex-col px-4 py-6 md:px-12 md:py-10">
        <div className="flex items-center justify-between lg:justify-end">
          <span className="lg:hidden">
            <Marque />
          </span>
          <BoutonTheme />
        </div>

        <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-8">
          <h1 className="text-3xl font-semibold text-text">Connexion</h1>
          <p className="mt-2 text-base text-muted">Accédez à votre espace avec votre compte.</p>

          <form onSubmit={soumettre} className="mt-8 space-y-5" noValidate>
            {erreur && (
              <Notice ton="erreur" titre="Connexion impossible" ref={refErreur}>
                {erreur}
              </Notice>
            )}
            <Input
              libelle="Adresse e-mail ou identifiant"
              aide="Citoyen : votre identifiant (par exemple BF-0001)."
              type="text"
              name="email"
              autoComplete="username"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
            <div>
              <Input
                libelle="Mot de passe"
                type={afficher ? 'text' : 'password'}
                name="mot_de_passe"
                autoComplete="current-password"
                required
                value={motDePasse}
                onChange={(e) => setMotDePasse(e.target.value)}
              />
              <button
                type="button"
                onClick={() => setAfficher((v) => !v)}
                aria-pressed={afficher}
                className="mt-2 inline-flex min-h-9 items-center gap-2 rounded-control text-sm font-medium text-accent hover:underline"
              >
                {afficher ? <EyeSlash size={18} aria-hidden="true" /> : <Eye size={18} aria-hidden="true" />}
                {afficher ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
              </button>
            </div>
            <Bouton type="submit" chargement={envoi} className="w-full">
              Se connecter
            </Bouton>
          </form>

          <details className="mt-8 rounded-panel border border-border bg-surface">
            <summary className="flex min-h-11 cursor-pointer items-center px-4 text-sm font-medium text-text">
              Comptes de démonstration (fictifs)
            </summary>
            <div className="border-t border-border px-4 py-3">
              <p className="mb-3 text-sm text-muted">
                Tous les comptes ont le mot de passe <span className="mono text-text">{MOT_DE_PASSE_DEMO}</span>.
                Choisissez un rôle pour remplir le formulaire.
              </p>
              <ul className="divide-y divide-border">
                {COMPTES_DEMO.map((c) => (
                  <li key={c.email} className="flex items-center justify-between gap-3 py-2">
                    <span className="text-sm text-text">{c.role}</span>
                    <Bouton
                      variante="secondaire"
                      taille="s"
                      onClick={() => {
                        setEmail(c.email)
                        setMotDePasse(MOT_DE_PASSE_DEMO)
                        setErreur(null)
                      }}
                      aria-label={`Utiliser le compte : ${c.role}`}
                    >
                      Utiliser
                    </Bouton>
                  </li>
                ))}
              </ul>
            </div>
          </details>

          <nav aria-label="Pages publiques" className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <Link to="/verifier" className="inline-flex min-h-9 items-center gap-2 font-medium text-accent hover:underline">
              <SealCheck size={18} aria-hidden="true" />
              Vérifier une attestation
            </Link>
            <Link to="/catalogue" className="inline-flex min-h-9 items-center gap-2 font-medium text-accent hover:underline">
              <BookOpenText size={18} aria-hidden="true" />
              Catalogue des questions
            </Link>
          </nav>
        </div>
      </main>
    </div>
  )
}
