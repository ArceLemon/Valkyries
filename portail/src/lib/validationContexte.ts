import { createContext, useContext } from 'react'

/**
 * Affiche une animation « chargement, puis validé » le temps d'une action.
 * Si l'action échoue, l'animation disparaît et l'erreur est relancée à l'appelant.
 */
export type Valider = <T>(travail: Promise<T>, message: string) => Promise<T>

export const ValidationContexte = createContext<Valider | null>(null)

export function useValidation(): Valider {
  const valider = useContext(ValidationContexte)
  if (!valider) throw new Error('useValidation doit être utilisé dans <ValidationProvider>.')
  return valider
}
