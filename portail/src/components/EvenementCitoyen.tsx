import { dateHeure } from '../lib/format'
import type { ConsultationCitoyen } from '../lib/types'
import { BadgeRegime, BadgeReponse } from './Pastille'

function phrase(e: ConsultationCitoyen): string {
  const question = e.question ?? 'une information'
  switch (e.type) {
    case 'consentement.accepte':
      return `Vous avez accepté : « ${question} »`
    case 'consentement.refuse':
      return `Vous avez refusé : « ${question} »`
    case 'consentement.retire':
      return `Vous avez retiré votre accord : « ${question} »`
    default:
      return `A vérifié : « ${question} »`
  }
}

/** Une ligne de « qui a consulté mes données » : organisme, question, finalité, réponse. */
export function EvenementCitoyen({ e }: { e: ConsultationCitoyen }) {
  const consultation = e.type === 'donnee.consultee'
  return (
    <li className="py-4 first:pt-0 last:pb-0">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
        <p className="text-base font-semibold text-text">{e.organisme ?? 'Organisme inconnu'}</p>
        <p className="text-sm text-muted">{dateHeure(e.date)}</p>
      </div>
      <p className="mt-1 text-base text-text">{phrase(e)}</p>
      {e.finalite && <p className="mt-1 text-sm text-muted">Finalité : {e.finalite}</p>}
      {consultation && (
        <div className="mt-3 flex flex-wrap items-center gap-2">
          {e.regime && <BadgeRegime regime={e.regime} />}
          {e.reponse && <BadgeReponse reponse={e.reponse as 'oui' | 'non' | 'impossible'} />}
        </div>
      )}
    </li>
  )
}
