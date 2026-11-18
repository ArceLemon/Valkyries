import { List, SignOut } from '@phosphor-icons/react'
import { Suspense, useEffect, useRef, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { Bouton } from '../components/Bouton'
import { BoutonTheme } from '../components/BoutonTheme'
import { Chargement } from '../components/Etats'
import { Marque } from '../components/Marque'
import { useAuth } from '../lib/auth'
import { cx } from '../lib/cx'
import { libelleRole } from '../lib/format'
import { navigationDuRole } from '../lib/nav'
import type { GroupeNav } from '../lib/nav'
import type { Utilisateur } from '../lib/types'

function Navigation({ groupes }: { groupes: GroupeNav[] }) {
  return (
    <nav aria-label="Navigation principale" className="flex flex-col gap-6">
      {groupes.map((groupe, i) => (
        <div key={i}>
          {groupe.titre && <p className="mb-2 px-3 text-sm font-medium text-muted">{groupe.titre}</p>}
          <ul className="flex flex-col gap-1">
            {groupe.liens.map(({ chemin, libelle, icone: Icone, exact }) => (
              <li key={chemin}>
                <NavLink
                  to={chemin}
                  end={exact}
                  className={({ isActive }) =>
                    cx(
                      'flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-medium transition-colors',
                      isActive
                        ? 'bg-accent text-accent-contrast'
                        : 'text-text hover:bg-surface-2',
                    )
                  }
                >
                  <Icone size={20} aria-hidden="true" />
                  {libelle}
                </NavLink>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </nav>
  )
}

function BlocUtilisateur({ utilisateur, onDeconnexion }: { utilisateur: Utilisateur; onDeconnexion: () => void }) {
  return (
    <div className="border-t border-border p-4">
      <p className="truncate text-sm font-semibold text-text">{utilisateur.nom}</p>
      <p className="text-sm text-muted">{libelleRole(utilisateur.role)}</p>
      {utilisateur.organisme && <p className="truncate text-sm text-muted">{utilisateur.organisme.nom}</p>}
      <div className="mt-3 flex items-center gap-2">
        <Bouton variante="secondaire" taille="s" onClick={onDeconnexion} icone={<SignOut size={18} />} className="flex-1">
          Déconnexion
        </Bouton>
        <BoutonTheme />
      </div>
    </div>
  )
}

export function Shell() {
  const { utilisateur, deconnexion } = useAuth()
  const tiroir = useRef<HTMLDialogElement>(null)
  const { pathname } = useLocation()
  // Le tiroir est ouvert « pour une page » : changer de page le referme sans effet de bord.
  const [ouvertSur, setOuvertSur] = useState<string | null>(null)
  const menuOuvert = ouvertSur === pathname
  const setMenuOuvert = (ouvert: boolean) => setOuvertSur(ouvert ? pathname : null)

  // Chaque changement de page remet le focus sur le contenu et revient en haut.
  useEffect(() => {
    document.getElementById('contenu')?.focus({ preventScroll: true })
    window.scrollTo({ top: 0 })
  }, [pathname])

  useEffect(() => {
    const dialogue = tiroir.current
    if (!dialogue) return
    if (menuOuvert && !dialogue.open) dialogue.showModal()
    if (!menuOuvert && dialogue.open) dialogue.close()
  }, [menuOuvert])

  if (!utilisateur) return null

  const groupes = navigationDuRole(utilisateur.role)
  const citoyen = utilisateur.role === 'citoyen'
  const liensBas = groupes.flatMap((g) => g.liens)

  return (
    <div className="min-h-dvh md:grid md:grid-cols-[17rem_1fr]">
      <a
        href="#contenu"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-control focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-contrast"
      >
        Aller au contenu
      </a>
      <div className="bande-drapeau fixed inset-x-0 top-0 z-30" aria-hidden="true" />

      {/* Barre du haut sur mobile */}
      <header className="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-border bg-surface px-4 md:hidden">
        <Marque taille={32} />
        <div className="flex items-center gap-1">
          <BoutonTheme />
          {citoyen ? (
            <Bouton variante="discret" taille="s" onClick={() => void deconnexion()} aria-label="Se déconnecter" icone={<SignOut size={20} />} />
          ) : (
            <Bouton variante="discret" taille="s" onClick={() => setMenuOuvert(true)} aria-label="Ouvrir le menu" icone={<List size={22} />} />
          )}
        </div>
      </header>

      {/* Barre latérale sur ordinateur */}
      <aside className="sticky top-0 hidden h-dvh flex-col border-r border-border bg-surface md:flex">
        <div className="px-5 py-5">
          <Marque />
        </div>
        <div className="flex-1 overflow-y-auto px-3 pb-4">
          <Navigation groupes={groupes} />
        </div>
        <BlocUtilisateur utilisateur={utilisateur} onDeconnexion={() => void deconnexion()} />
      </aside>

      <main
        id="contenu"
        tabIndex={-1}
        className={cx('min-w-0 px-4 py-6 outline-none md:px-10 md:py-10', citoyen && 'pb-28 md:pb-10')}
      >
        <div className="mx-auto max-w-[1100px]">
          <Suspense fallback={<Chargement lignes={3} />}>
            <Outlet />
          </Suspense>
        </div>
      </main>

      {/* Barre du bas pour le citoyen : cinq destinations, toutes visibles */}
      {citoyen && (
        <nav
          aria-label="Navigation principale"
          className="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)] md:hidden"
        >
          {liensBas.map(({ chemin, libelle, court, icone: Icone, exact }) => (
            <NavLink
              key={chemin}
              to={chemin}
              end={exact}
              className={({ isActive }) =>
                cx(
                  'flex min-h-16 flex-col items-center justify-center gap-1 px-1 text-xs font-medium',
                  isActive ? 'text-accent' : 'text-muted',
                )
              }
            >
              <Icone size={24} aria-hidden="true" />
              {court ?? libelle}
            </NavLink>
          ))}
        </nav>
      )}

      {/* Tiroir de navigation sur mobile (autres rôles) */}
      <dialog
        ref={tiroir}
        onClose={() => setMenuOuvert(false)}
        aria-label="Menu"
        className="m-0 mr-auto h-dvh max-h-dvh w-72 max-w-[85vw] overflow-y-auto rounded-none border-r border-border bg-surface p-0 text-text backdrop:bg-black/50"
      >
        <div className="flex min-h-full flex-col">
          <div className="flex items-center justify-between px-4 py-4">
            <Marque taille={32} />
          </div>
          <div className="flex-1 px-3 pb-4">
            <Navigation groupes={groupes} />
          </div>
          <BlocUtilisateur utilisateur={utilisateur} onDeconnexion={() => void deconnexion()} />
        </div>
      </dialog>
    </div>
  )
}
