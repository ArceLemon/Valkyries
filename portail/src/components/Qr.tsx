import { toDataURL } from 'qrcode'
import { useEffect, useState } from 'react'

/** Code QR généré localement (aucun service externe). */
export function Qr({ valeur, taille = 192, titre }: { valeur: string; taille?: number; titre?: string }) {
  const [source, setSource] = useState<string | null>(null)

  useEffect(() => {
    let actif = true
    toDataURL(valeur, {
      margin: 1,
      width: taille * 2,
      errorCorrectionLevel: 'L',
      color: { dark: '#023e58', light: '#ffffff' },
    })
      .then((url) => actif && setSource(url))
      .catch(() => actif && setSource(null))
    return () => {
      actif = false
    }
  }, [valeur, taille])

  if (source === null) {
    return <div className="rounded-control bg-surface-2" style={{ width: taille, height: taille }} aria-hidden="true" />
  }

  return (
    <img
      src={source}
      width={taille}
      height={taille}
      alt={titre ?? 'Code QR de vérification'}
      className="rounded-control border border-border bg-white p-1"
    />
  )
}
