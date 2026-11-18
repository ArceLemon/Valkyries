import { Moon, Sun } from '@phosphor-icons/react'
import { useState } from 'react'
import { Bouton } from './Bouton'

type Theme = 'light' | 'dark'

const CLE = 'kumabana.theme'

/** Le thème est clair par défaut, quel que soit celui du système : seul un choix explicite le change. */
function themeEffectif(): Theme {
  return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light'
}

/** À appeler une fois au démarrage : applique le thème mémorisé, sinon le thème clair. */
export function initialiserTheme(): void {
  let theme: Theme = 'light'
  try {
    if (localStorage.getItem(CLE) === 'dark') theme = 'dark'
  } catch {
    // Pas de stockage : thème clair.
  }
  document.documentElement.dataset.theme = theme
}

export function BoutonTheme() {
  const [theme, setTheme] = useState<Theme>(themeEffectif)

  function basculer() {
    const suivant: Theme = theme === 'dark' ? 'light' : 'dark'
    document.documentElement.dataset.theme = suivant
    try {
      localStorage.setItem(CLE, suivant)
    } catch {
      // Le choix ne sera pas mémorisé.
    }
    setTheme(suivant)
  }

  return (
    <Bouton
      variante="discret"
      taille="s"
      onClick={basculer}
      aria-label={theme === 'dark' ? 'Passer au thème clair' : 'Passer au thème sombre'}
      icone={theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
    />
  )
}
