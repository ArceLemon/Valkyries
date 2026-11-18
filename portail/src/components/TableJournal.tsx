import { dateHeure, libelleAction, libelleRegistre, libelleResultat, libelleRole } from '../lib/format'
import type { LigneJournal } from '../lib/types'
import { Tableau, Td, Th } from './Tableau'

function acteur(ligne: LigneJournal): string {
  if (ligne.acteur_type === 'systeme') return 'Système'
  if (ligne.acteur_type === 'inconnu') return 'Inconnu'
  const role = libelleRole(ligne.acteur_type)
  return ligne.acteur_id ? `${role} n°${ligne.acteur_id}` : role
}

/** Résumé lisible du détail d'une ligne : question, finalité, registre. Jamais de donnée brute. */
function resume(ligne: LigneJournal, questions: Record<string, string>): string {
  const d = ligne.details
  const morceaux: string[] = []
  if (typeof d.question === 'string') morceaux.push(questions[d.question] ?? d.question)
  if (typeof d.finalite === 'string') morceaux.push(d.finalite)
  if (typeof d.registre === 'string') morceaux.push(libelleRegistre(d.registre))
  if (typeof d.cause === 'string') morceaux.push(d.cause.replace(/_/g, ' '))
  return morceaux.join(', ')
}

export function TableJournal({
  lignes,
  questions = {},
  avecSujet = false,
}: {
  lignes: LigneJournal[]
  /** code de question => libellé, pour afficher « Est majeur » plutôt que « majeur » */
  questions?: Record<string, string>
  avecSujet?: boolean
}) {
  return (
    <Tableau legende="Journal des événements, du plus récent au plus ancien">
      <thead>
        <tr>
          <Th>Date</Th>
          <Th>Événement</Th>
          <Th className="hidden md:table-cell">Acteur</Th>
          <Th className="hidden lg:table-cell">Détail</Th>
          {avecSujet && <Th className="hidden lg:table-cell">Citoyen (empreinte)</Th>}
          <Th className="hidden md:table-cell">Résultat</Th>
        </tr>
      </thead>
      <tbody>
        {lignes.map((l) => (
          <tr key={l.id}>
            <Td className="whitespace-nowrap">{dateHeure(l.date)}</Td>
            <Td>
              <span className="font-medium text-text">{libelleAction(l.action)}</span>
              <span className="mt-0.5 block text-muted md:hidden">{acteur(l)}</span>
            </Td>
            <Td className="hidden md:table-cell">{acteur(l)}</Td>
            <Td className="hidden max-w-[22rem] text-muted lg:table-cell">{resume(l, questions) || '-'}</Td>
            {avecSujet && <Td className="mono hidden text-muted lg:table-cell">{l.sujet ?? '-'}</Td>}
            <Td className="hidden md:table-cell">{libelleResultat(l.resultat)}</Td>
          </tr>
        ))}
      </tbody>
    </Tableau>
  )
}
