import {
  ArrowsClockwise,
  CheckCircle,
  Circle,
  Clock,
  Handshake,
  HourglassMedium,
  Info,
  LockKey,
  MinusCircle,
  Timer,
  WarningCircle,
  XCircle,
} from '@phosphor-icons/react'
import type { Icon } from '@phosphor-icons/react'
import type { ReactNode } from 'react'
import { cx } from '../lib/cx'
import { LIBELLE_STATUT } from '../lib/format'
import type { EtatRegistre, Reponse, StatutDemande } from '../lib/types'

export type Ton = 'ok' | 'attente' | 'refus' | 'neutre' | 'info'

const tons: Record<Ton, string> = {
  ok: 'bg-ok-soft text-ok-soft-text',
  info: 'bg-accent-soft text-accent-soft-text',
  attente: 'bg-warn-soft text-warn-soft-text',
  refus: 'bg-danger-soft text-danger-soft-text',
  neutre: 'border border-border bg-surface-2 text-text',
}

/**
 * Une pastille d'état. La couleur n'est jamais seule : une icône et un texte
 * disent la même chose.
 */
export function Pastille({
  ton,
  icone: Icone,
  children,
  className,
}: {
  ton: Ton
  icone?: Icon
  children: ReactNode
  className?: string
}) {
  return (
    <span
      className={cx(
        'inline-flex items-center gap-1.5 rounded-control px-2.5 py-1 text-sm font-medium whitespace-nowrap',
        tons[ton],
        className,
      )}
    >
      {Icone && <Icone size={16} aria-hidden="true" />}
      {children}
    </span>
  )
}

const statuts: Record<StatutDemande, { ton: Ton; icone: Icon }> = {
  recue: { ton: 'neutre', icone: Circle },
  en_attente_consentement: { ton: 'attente', icone: Clock },
  en_cours: { ton: 'info', icone: ArrowsClockwise },
  en_attente_registre: { ton: 'attente', icone: HourglassMedium },
  terminee: { ton: 'ok', icone: CheckCircle },
  ambigue: { ton: 'attente', icone: WarningCircle },
  refusee_citoyen: { ton: 'refus', icone: XCircle },
  expiree: { ton: 'neutre', icone: Timer },
}

export function BadgeStatut({ statut }: { statut: StatutDemande }) {
  const s = statuts[statut]
  return (
    <Pastille ton={s.ton} icone={s.icone}>
      {LIBELLE_STATUT[statut]}
    </Pastille>
  )
}

export function BadgeReponse({ reponse }: { reponse: Reponse }) {
  if (reponse === 'oui') return <Pastille ton="ok" icone={CheckCircle}>Oui</Pastille>
  if (reponse === 'non') return <Pastille ton="neutre" icone={MinusCircle}>Non</Pastille>
  if (reponse === 'impossible') {
    return <Pastille ton="attente" icone={WarningCircle}>Impossible</Pastille>
  }
  return <span className="text-muted">-</span>
}

export function BadgeRegistre({ etat }: { etat: EtatRegistre }) {
  if (etat === 'en_service') return <Pastille ton="ok" icone={CheckCircle}>En service</Pastille>
  if (etat === 'lent') return <Pastille ton="attente" icone={Clock}>Lent</Pastille>
  return <Pastille ton="refus" icone={XCircle}>En panne</Pastille>
}

export function BadgeRegime({ regime }: { regime: string }) {
  return regime === 'consentement' ? (
    <Pastille ton="info" icone={Handshake}>Consentement requis</Pastille>
  ) : (
    <Pastille ton="neutre" icone={Info}>Citoyen informé</Pastille>
  )
}

export function BadgeSensibilite({ sensibilite }: { sensibilite: string }) {
  return sensibilite === 'elevee' ? (
    <Pastille ton="attente" icone={LockKey}>Sensibilité élevée</Pastille>
  ) : (
    <Pastille ton="neutre">Sensibilité normale</Pastille>
  )
}

export function BadgeEtatDroit({ etat }: { etat: 'en_attente' | 'valide' | 'expire' }) {
  if (etat === 'valide') return <Pastille ton="ok" icone={CheckCircle}>Valide</Pastille>
  if (etat === 'expire') return <Pastille ton="neutre" icone={Timer}>Expiré</Pastille>
  return <Pastille ton="attente" icone={Clock}>En attente de la source</Pastille>
}

export function BadgeActif({ actif }: { actif: boolean }) {
  return actif ? (
    <Pastille ton="ok" icone={CheckCircle}>Actif</Pastille>
  ) : (
    <Pastille ton="refus" icone={XCircle}>Révoqué</Pastille>
  )
}
