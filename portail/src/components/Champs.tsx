import { useId } from 'react'
import type {
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react'
import { cx } from '../lib/cx'

interface Base {
  libelle: string
  aide?: string
  erreur?: string
}

function Enveloppe({
  id,
  libelle,
  aide,
  erreur,
  requis,
  children,
}: Base & { id: string; requis?: boolean; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={id} className="text-sm font-medium text-text">
        {libelle}
        {requis && (
          <span aria-hidden="true" className="text-muted">
            {' '}
            *
          </span>
        )}
      </label>
      {children}
      {aide && (
        <p id={`${id}-aide`} className="text-sm text-muted">
          {aide}
        </p>
      )}
      {erreur && (
        <p id={`${id}-erreur`} role="alert" className="text-sm font-medium text-danger-soft-text">
          {erreur}
        </p>
      )}
    </div>
  )
}

function decrit(id: string, aide?: string, erreur?: string): string | undefined {
  const ids = [aide ? `${id}-aide` : null, erreur ? `${id}-erreur` : null].filter(Boolean)
  return ids.length > 0 ? ids.join(' ') : undefined
}

const champ =
  'w-full rounded-control border bg-surface px-3 text-base text-text placeholder:text-muted ' +
  'focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus disabled:opacity-60'

function bordure(erreur?: string): string {
  return erreur ? 'border-danger' : 'border-border-strong'
}

export function Input({
  libelle,
  aide,
  erreur,
  className,
  required,
  ...reste
}: Base & InputHTMLAttributes<HTMLInputElement>) {
  const id = useId()
  return (
    <Enveloppe id={id} libelle={libelle} aide={aide} erreur={erreur} requis={required}>
      <input
        id={id}
        required={required}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={decrit(id, aide, erreur)}
        className={cx(champ, 'h-11', bordure(erreur), className)}
        {...reste}
      />
    </Enveloppe>
  )
}

export function Select({
  libelle,
  aide,
  erreur,
  className,
  required,
  children,
  ...reste
}: Base & SelectHTMLAttributes<HTMLSelectElement>) {
  const id = useId()
  return (
    <Enveloppe id={id} libelle={libelle} aide={aide} erreur={erreur} requis={required}>
      <select
        id={id}
        required={required}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={decrit(id, aide, erreur)}
        className={cx(champ, 'h-11', bordure(erreur), className)}
        {...reste}
      >
        {children}
      </select>
    </Enveloppe>
  )
}

export function Textarea({
  libelle,
  aide,
  erreur,
  className,
  required,
  ...reste
}: Base & TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const id = useId()
  return (
    <Enveloppe id={id} libelle={libelle} aide={aide} erreur={erreur} requis={required}>
      <textarea
        id={id}
        required={required}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={decrit(id, aide, erreur)}
        className={cx(champ, 'min-h-24 py-2', bordure(erreur), className)}
        {...reste}
      />
    </Enveloppe>
  )
}

export function Case({
  libelle,
  aide,
  className,
  ...reste
}: { libelle: string; aide?: string } & Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>) {
  const id = useId()
  return (
    <div className={cx('flex items-start gap-3 py-1', className)}>
      <input
        id={id}
        type="checkbox"
        aria-describedby={aide ? `${id}-aide` : undefined}
        className="mt-0.5 size-5 shrink-0 accent-accent"
        {...reste}
      />
      <div>
        <label htmlFor={id} className="text-sm font-medium text-text">
          {libelle}
        </label>
        {aide && (
          <p id={`${id}-aide`} className="text-sm text-muted">
            {aide}
          </p>
        )}
      </div>
    </div>
  )
}
