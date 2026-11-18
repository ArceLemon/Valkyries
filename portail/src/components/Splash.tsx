import symbole from '../assets/kumabana-mark.png'

/**
 * Écran de chargement de la marque. Il s'affiche une fois par session, le temps que
 * l'application démarre : dégradé bleu du logo, ondes, symbole qui respire, et une
 * barre aux couleurs du pays (jaune, rouge, vert).
 */
export function Splash({
  sortie = false,
  onFini,
  message = 'Chargement en cours',
}: {
  sortie?: boolean
  onFini?: () => void
  message?: string
}) {
  return (
    <div
      className={sortie ? 'splash splash-sortie' : 'splash'}
      role="status"
      aria-live="polite"
      onAnimationEnd={(e) => {
        if (sortie && e.target === e.currentTarget) onFini?.()
      }}
    >
      <span className="sr-only">{message}</span>

      <div aria-hidden="true">
        <span className="splash-soleil" style={{ width: 460, height: 460, right: -140, bottom: -160, background: '#7dd6f0' }} />
        <span className="splash-soleil" style={{ width: 380, height: 380, left: '25%', top: '-20%', background: '#3fb8e8' }} />
        <span className="splash-anneau" style={{ width: 560, height: 560, right: -230, top: -230, borderColor: 'rgba(252,195,4,0.85)' }} />
        <span className="splash-anneau" style={{ width: 360, height: 360, right: -110, top: -110, borderColor: 'rgba(255,255,255,0.22)', borderWidth: 3 }} />
        <span className="splash-anneau" style={{ width: 480, height: 480, left: -200, bottom: -200, borderColor: 'rgba(235,21,37,0.8)' }} />
        <span className="splash-anneau" style={{ width: 300, height: 300, left: -90, bottom: -90, borderColor: 'rgba(255,255,255,0.22)', borderWidth: 3 }} />
        <span className="splash-cercle" style={{ width: 360, height: 360 }} />
        <span className="splash-cercle" style={{ width: 360, height: 360 }} />
        <span className="splash-cercle" style={{ width: 360, height: 360 }} />
      </div>

      <div className="relative flex flex-col items-center gap-6 px-6 text-center">
        <div className="splash-marque">
          <img src={symbole} alt="" />
        </div>
        <p className="splash-titre text-4xl font-bold tracking-tight">KUMA BANA</p>
        <p className="splash-devise max-w-[30ch] text-lg text-white/90">
          Une administration connectée, un citoyen au cœur
        </p>
        <div className="splash-barre" aria-hidden="true" />
      </div>

      <p className="absolute bottom-8 text-sm font-medium text-white/80">Burkina Faso</p>
    </div>
  )
}
