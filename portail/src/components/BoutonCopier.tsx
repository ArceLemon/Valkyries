import { Check, Copy } from '@phosphor-icons/react'
import { useState } from 'react'
import { Bouton } from './Bouton'

export function BoutonCopier({ valeur, libelle = 'Copier' }: { valeur: string; libelle?: string }) {
  const [copie, setCopie] = useState(false)

  async function copier() {
    try {
      await navigator.clipboard.writeText(valeur)
      setCopie(true)
      setTimeout(() => setCopie(false), 2000)
    } catch {
      // Presse-papiers indisponible : l'utilisateur peut sélectionner le texte.
    }
  }

  return (
    <Bouton
      variante="secondaire"
      taille="s"
      onClick={copier}
      icone={copie ? <Check size={18} /> : <Copy size={18} />}
    >
      <span aria-live="polite">{copie ? 'Copié' : libelle}</span>
    </Bouton>
  )
}
