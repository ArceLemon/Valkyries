import { MagnifyingGlass } from '@phosphor-icons/react'
import { Link } from 'react-router-dom'
import { classesBouton } from '../components/Bouton'
import { Vide } from '../components/Etats'
import { useTitre } from '../lib/useTitre'

export function Page404() {
  useTitre('Page introuvable')
  return (
    <main className="mx-auto max-w-xl px-4 py-24">
      <Vide
        icone={MagnifyingGlass}
        titre="Cette page n'existe pas"
        texte="Le lien est peut-être ancien, ou la page est réservée à un autre profil."
        action={
          <Link to="/" className={classesBouton('primaire')}>
            Retour à l'accueil
          </Link>
        }
      />
    </main>
  )
}
