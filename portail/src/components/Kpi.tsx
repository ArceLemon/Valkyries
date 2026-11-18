import { ArrowDownRight, ArrowUpRight, Minus } from '@phosphor-icons/react'
import type { Icon } from '@phosphor-icons/react'
import type { ReactNode } from 'react'
import { cx } from '../lib/cx'

export type Ton = 'bleu' | 'jaune' | 'vert' | 'rouge'

/** Teinte de fond, de texte et de tracé de chaque couleur de la charte. */
const TONS: Record<Ton, { puce: string; trace: string; barre: string }> = {
  bleu: { puce: 'bg-accent text-white', trace: 'text-accent', barre: 'bg-accent' },
  jaune: { puce: 'bg-warn text-[#3a2a00]', trace: 'text-[#c99700]', barre: 'bg-warn' },
  vert: { puce: 'bg-ok text-white', trace: 'text-ok', barre: 'bg-ok' },
  rouge: { puce: 'bg-danger text-white', trace: 'text-danger', barre: 'bg-danger' },
}

/** Durée lisible : « 4 s », « 3 min », « 2 h ». */
export function duree(secondes: number | null): string {
  if (secondes === null) return '-'
  if (secondes < 1) return '< 1 s'
  if (secondes < 60) return `${secondes} s`
  if (secondes < 3600) return `${Math.round(secondes / 60)} min`
  return `${Math.round(secondes / 3600)} h`
}

/** Petite courbe sans axes : la forme de l'activité, pas les valeurs. */
export function Sparkline({ valeurs, ton }: { valeurs: number[]; ton: Ton }) {
  const max = Math.max(1, ...valeurs)
  const l = 120
  const h = 36
  const pas = l / Math.max(1, valeurs.length - 1)
  const points = valeurs.map((v, i) => [i * pas, h - 3 - (v / max) * (h - 8)] as const)
  const ligne = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ')
  const aire = `${ligne} L${l},${h} L0,${h} Z`
  return (
    <svg viewBox={`0 0 ${l} ${h}`} className={cx('h-9 w-full', TONS[ton].trace)} aria-hidden="true" preserveAspectRatio="none">
      <path d={aire} fill="currentColor" opacity="0.14" />
      <path d={ligne} fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
    </svg>
  )
}

/** Évolution par rapport à la période précédente. */
export function Tendance({ actuel, precedent }: { actuel: number; precedent: number }) {
  if (precedent === 0 && actuel === 0) return null
  const delta = precedent === 0 ? 100 : Math.round(((actuel - precedent) / precedent) * 100)
  const Fleche = delta > 0 ? ArrowUpRight : delta < 0 ? ArrowDownRight : Minus
  return (
    <span
      className={cx(
        'inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold',
        delta > 0 ? 'bg-ok-soft text-ok-soft-text' : delta < 0 ? 'bg-danger-soft text-danger-soft-text' : 'bg-surface-2 text-muted',
      )}
    >
      <Fleche size={14} weight="bold" aria-hidden="true" />
      {delta > 0 ? '+' : ''}
      {delta}%<span className="sr-only"> sur 7 jours</span>
    </span>
  )
}

/** Un indicateur : une grande valeur, ce qu'elle mesure, et au besoin sa tendance ou sa courbe. */
export function Kpi({
  libelle,
  valeur,
  unite,
  detail,
  ton,
  icone: Icone,
  serie,
  tendance,
}: {
  libelle: string
  valeur: ReactNode
  unite?: string
  detail?: ReactNode
  ton: Ton
  icone: Icon
  serie?: number[]
  tendance?: ReactNode
}) {
  return (
    <div className="flex flex-col justify-between gap-3 rounded-panel border border-border bg-surface p-4 shadow-[0_6px_18px_rgba(10,123,162,0.07)]">
      <div className="flex items-start justify-between gap-3">
        <span className={cx('grid size-11 place-items-center rounded-control', TONS[ton].puce)}>
          <Icone size={24} weight="bold" aria-hidden="true" />
        </span>
        {tendance}
      </div>
      <div>
        <dd className="flex items-baseline gap-1.5 text-3xl font-bold sm:text-4xl leading-none text-text">
          {valeur}
          {unite && <span className="text-lg font-semibold text-muted">{unite}</span>}
        </dd>
        <dt className="mt-2 text-sm font-medium text-muted">{libelle}</dt>
        {detail && <p className="mt-0.5 text-xs text-muted">{detail}</p>}
      </div>
      {serie && <Sparkline valeurs={serie} ton={ton} />}
    </div>
  )
}

export function GrilleKpi({ children, colonnes = 4 }: { children: ReactNode; colonnes?: 3 | 4 }) {
  return (
    <dl className={cx('mb-6 grid grid-cols-2 gap-3 sm:gap-4', colonnes === 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-3')}>
      {children}
    </dl>
  )
}

/** Jauge circulaire : un pourcentage, sans phrase. */
export function Jauge({ valeur, ton }: { valeur: number | null; ton: Ton }) {
  const r = 38
  const c = 2 * Math.PI * r
  const v = valeur ?? 0
  return (
    <svg viewBox="0 0 100 100" className={cx('size-28', TONS[ton].trace)} role="img" aria-label={valeur === null ? 'Pas encore de donnée' : `${valeur} pour cent`}>
      <circle cx="50" cy="50" r={r} fill="none" stroke="currentColor" strokeWidth="11" opacity="0.15" />
      <circle
        cx="50"
        cy="50"
        r={r}
        fill="none"
        stroke="currentColor"
        strokeWidth="11"
        strokeLinecap="round"
        strokeDasharray={`${(c * v) / 100} ${c}`}
        transform="rotate(-90 50 50)"
      />
      <text x="50" y="57" textAnchor="middle" className="fill-text text-[17px] font-bold">
        {valeur === null ? '-' : `${valeur}%`}
      </text>
    </svg>
  )
}

/** Barres par jour : le total, avec la part de réponses obtenues en plus foncé. */
export function GrapheJours({ serie }: { serie: { date: string; total: number; obtenues: number }[] }) {
  const max = Math.max(1, ...serie.map((s) => s.total))
  const jours = ['D', 'L', 'M', 'M', 'J', 'V', 'S']
  const aujourdhui = serie[serie.length - 1]?.date
  return (
    <figure>
      <div className="flex h-44 items-end gap-1.5 sm:gap-2" role="img" aria-label={`Demandes par jour sur ${serie.length} jours : ${serie.map((s) => s.total).join(', ')}`}>
        {serie.map((s) => (
          <div key={s.date} className="group flex h-full min-w-0 flex-1 flex-col justify-end" title={`${s.date} : ${s.total} demande(s), ${s.obtenues} réponse(s)`}>
            <span className="mb-1 text-center text-[11px] font-semibold text-muted">{s.total || ''}</span>
            <div
              className={cx('relative w-full overflow-hidden rounded-t-md', s.date === aujourdhui ? 'bg-sky' : 'bg-accent-soft')}
              style={{ height: `${Math.max(s.total ? 6 : 2, (s.total / max) * 100)}%` }}
            >
              <div className="absolute inset-x-0 bottom-0 bg-accent" style={{ height: s.total ? `${(s.obtenues / s.total) * 100}%` : 0 }} />
            </div>
          </div>
        ))}
      </div>
      <div className="mt-2 flex gap-1.5 sm:gap-2" aria-hidden="true">
        {serie.map((s) => (
          <span key={s.date} className={cx('min-w-0 flex-1 text-center text-xs', s.date === aujourdhui ? 'font-bold text-text' : 'text-muted')}>
            {jours[new Date(`${s.date}T12:00:00`).getDay()]}
          </span>
        ))}
      </div>
      <figcaption className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted">
        <span className="inline-flex items-center gap-1.5">
          <span className="size-3 rounded-sm bg-accent" aria-hidden="true" /> Réponses obtenues
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="size-3 rounded-sm bg-accent-soft ring-1 ring-border-strong" aria-hidden="true" /> Autres demandes
        </span>
      </figcaption>
    </figure>
  )
}

export interface Part {
  libelle: string
  valeur: number
  ton: Ton | 'gris'
}

const FOND_PART: Record<Part['ton'], string> = {
  bleu: 'bg-accent',
  jaune: 'bg-warn',
  vert: 'bg-ok',
  rouge: 'bg-danger',
  gris: 'bg-border-strong',
}

/** Une barre découpée en parts, avec la légende chiffrée dessous. */
export function Repartition({ parts }: { parts: Part[] }) {
  const total = parts.reduce((t, p) => t + p.valeur, 0)
  if (total === 0) return <p className="text-sm text-muted">Pas encore de donnée.</p>
  return (
    <div>
      <div className="flex h-4 overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
        {parts
          .filter((p) => p.valeur > 0)
          .map((p) => (
            <span key={p.libelle} className={FOND_PART[p.ton]} style={{ width: `${(p.valeur / total) * 100}%` }} />
          ))}
      </div>
      <ul className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2">
        {parts.map((p) => (
          <li key={p.libelle} className="flex items-center justify-between gap-2 text-sm">
            <span className="inline-flex min-w-0 items-center gap-2 text-muted">
              <span className={cx('size-3 shrink-0 rounded-sm', FOND_PART[p.ton])} aria-hidden="true" />
              <span className="truncate">{p.libelle}</span>
            </span>
            <span className="font-semibold text-text">{p.valeur}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

/** Classement : une barre horizontale par ligne, proportionnelle au plus grand. */
export function Classement({ lignes }: { lignes: { libelle: string; valeur: number }[] }) {
  const max = Math.max(1, ...lignes.map((l) => l.valeur))
  if (lignes.length === 0) return <p className="text-sm text-muted">Pas encore de donnée.</p>
  return (
    <ol className="space-y-3">
      {lignes.map((l, i) => (
        <li key={l.libelle}>
          <div className="mb-1 flex items-center justify-between gap-3 text-sm">
            <span className="truncate text-text">{l.libelle}</span>
            <span className="font-semibold text-text">{l.valeur}</span>
          </div>
          <div className="h-2.5 overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
            <div
              className={cx('h-full rounded-full', ['bg-accent', 'bg-sky', 'bg-ok', 'bg-warn', 'bg-danger'][i % 5])}
              style={{ width: `${(l.valeur / max) * 100}%` }}
            />
          </div>
        </li>
      ))}
    </ol>
  )
}
