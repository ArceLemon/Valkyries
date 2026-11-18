import symbole from '../assets/kumabana-mark.png'
import { cx } from '../lib/cx'

/**
 * Symbole du logo, posé sur une pastille blanche : le logo est conçu pour un fond
 * clair. `clair` écrit le nom en blanc, pour un fond coloré.
 */
export function Marque({
  taille = 36,
  texte = true,
  clair = false,
}: {
  taille?: number
  texte?: boolean
  clair?: boolean
}) {
  return (
    <span className="inline-flex items-center gap-3">
      <span
        className="grid shrink-0 place-items-center rounded-control bg-logo-plate p-1 shadow-sm"
        style={{ width: taille, height: taille }}
      >
        <img src={symbole} alt={texte ? '' : 'KUMA BANA'} className="h-full w-full object-contain" />
      </span>
      {texte && (
        <span className={cx('text-lg font-bold tracking-tight', clair ? 'text-white' : 'text-text')}>KUMA BANA</span>
      )}
    </span>
  )
}
