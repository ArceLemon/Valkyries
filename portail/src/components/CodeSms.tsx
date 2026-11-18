import { DeviceMobile } from '@phosphor-icons/react'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { api, messageErreur } from '../lib/api'
import type { Demande } from '../lib/types'
import { useValidation } from '../lib/validationContexte'
import { Bouton } from './Bouton'
import { Input } from './Champs'
import { Panneau } from './Panneau'

/**
 * Le citoyen sans smartphone lit à l'agent le code reçu par SMS, l'agent le saisit ici.
 * Seul le détenteur du numéro enregistré a pu le lire : le code vaut accord.
 * L'agent ne voit jamais le SMS : il le reçoit de la bouche du citoyen.
 */
export function CodeSms({ demande, onAccepte }: { demande: Demande; onAccepte: () => void }) {
  const valider = useValidation()
  const [code, setCode] = useState('')
  const [erreur, setErreur] = useState<string | undefined>()
  const [envoi, setEnvoi] = useState(false)

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    if (!/^\d{6}$/.test(code)) {
      setErreur('Le code compte 6 chiffres.')
      return
    }
    setErreur(undefined)
    setEnvoi(true)
    try {
      await valider(
        api(`/demandes/${encodeURIComponent(demande.reference)}/code`, { method: 'POST', corps: { code } }),
        'Accord enregistré',
      )
      onAccepte()
    } catch (err) {
      setErreur(messageErreur(err))
      setCode('')
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Panneau
      titre="Le citoyen n'a pas l'application ?"
      description="Un SMS avec un code vient d'être envoyé sur son téléphone. Demandez-lui de vous le lire."
    >
      <form onSubmit={soumettre} noValidate className="max-w-sm space-y-4">
        <Input
          libelle="Code reçu par le citoyen"
          aide="6 chiffres. Valable 15 minutes, 3 essais."
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={6}
          value={code}
          onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
          erreur={erreur}
        />
        <Bouton type="submit" chargement={envoi} icone={<DeviceMobile size={20} aria-hidden="true" />}>
          Valider le code
        </Bouton>
      </form>
    </Panneau>
  )
}
