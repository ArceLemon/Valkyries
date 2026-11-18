import { ilYa, libelleRegistre } from '../lib/format'
import type { Registre } from '../lib/types'
import { BadgeRegistre } from './Pastille'

/** État en direct des registres : en service, lent ou en panne. */
export function ListeRegistres({ registres }: { registres: Registre[] }) {
  return (
    <ul className="divide-y divide-border">
      {registres.map((r) => (
        <li key={r.registre} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0">
          <div className="min-w-0">
            <p className="text-base font-medium text-text">{libelleRegistre(r.registre)}</p>
            <p className="text-sm text-muted">
              {r.latence_ms !== null ? `${r.latence_ms} ms, ` : ''}
              {r.verifie_le ? `vérifié ${ilYa(r.verifie_le)}` : 'pas encore vérifié'}
              {r.disjoncteur_ouvert && ' : appels suspendus quelques secondes pour ne pas le saturer'}
            </p>
          </div>
          <BadgeRegistre etat={r.etat} />
        </li>
      ))}
    </ul>
  )
}
