import { ArrowSquareOut } from '@phosphor-icons/react'
import { Link } from 'react-router-dom'
import { dateHeure, lienVerification } from '../lib/format'
import { classesBouton } from './Bouton'
import { BoutonCopier } from './BoutonCopier'
import { Qr } from './Qr'

/**
 * Attestation signée : le code QR et le lien ouvrent la page publique de
 * vérification. Rien de personnel n'y figure.
 */
export function BlocPreuve({ jeton, valideJusqua }: { jeton: string; valideJusqua?: string }) {
  const lien = lienVerification(jeton)
  return (
    <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
      <Qr valeur={lien} taille={232} />
      <div className="min-w-0 flex-1 space-y-4">
        {valideJusqua && (
          <p className="text-base text-text">
            Valable jusqu'au <strong className="font-semibold">{dateHeure(valideJusqua)}</strong>.
          </p>
        )}
        <p className="text-sm text-muted">
          Scannez le code ou ouvrez le lien : n'importe qui peut vérifier cette attestation, sans compte.
        </p>
        <div className="flex flex-wrap gap-2">
          <BoutonCopier valeur={lien} libelle="Copier le lien" />
          <Link
            to={`/verifier?jeton=${encodeURIComponent(jeton)}`}
            className={classesBouton('secondaire', 's')}
          >
            <ArrowSquareOut size={18} aria-hidden="true" />
            Ouvrir la vérification
          </Link>
        </div>
      </div>
    </div>
  )
}
