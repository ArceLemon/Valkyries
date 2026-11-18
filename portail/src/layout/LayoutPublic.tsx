import { Suspense } from 'react'
import { Link, Outlet } from 'react-router-dom'
import { classesBouton } from '../components/Bouton'
import { BoutonTheme } from '../components/BoutonTheme'
import { Chargement } from '../components/Etats'
import { Marque } from '../components/Marque'

const lien = 'inline-flex min-h-11 items-center rounded-control px-3 text-sm font-medium text-text hover:bg-surface-2'

/** Pages publiques : vérification d'une attestation et catalogue, sans compte. */
export function LayoutPublic() {
  return (
    <div className="flex min-h-dvh flex-col">
      <a
        href="#contenu"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-control focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-contrast"
      >
        Aller au contenu
      </a>
      <div className="bande-drapeau" aria-hidden="true" />
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-[1100px] flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 md:px-10">
          <Link to="/connexion" aria-label="KUMA BANA, accueil">
            <Marque />
          </Link>
          <nav aria-label="Navigation publique" className="flex flex-wrap items-center gap-1">
            <Link to="/catalogue" className={lien}>
              Catalogue
            </Link>
            <Link to="/verifier" className={lien}>
              Vérifier une attestation
            </Link>
            <Link to="/connexion" className={classesBouton('primaire', 's')}>
              Connexion
            </Link>
            <BoutonTheme />
          </nav>
        </div>
      </header>

      <main id="contenu" tabIndex={-1} className="mx-auto w-full max-w-[1100px] flex-1 px-4 py-8 outline-none md:px-10 md:py-12">
        <Suspense fallback={<Chargement lignes={3} />}>
          <Outlet />
        </Suspense>
      </main>

      <footer className="border-t border-border bg-surface">
        <p className="mx-auto max-w-[1100px] px-4 py-5 text-sm text-muted md:px-10">
          KUMA BANA est un logiciel libre. Ce site n'utilise ni cookie, ni traceur, ni service externe.
        </p>
      </footer>
    </div>
  )
}
