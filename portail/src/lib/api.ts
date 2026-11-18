// Accès à l'API. Le jeton est gardé dans le stockage de l'onglet (sessionStorage) :
// il disparaît à la fermeture de l'onglet. Aucun cookie, aucun traceur.

const CLE_JETON = 'kumabana.jeton'

export const EVENEMENT_SESSION_EXPIREE = 'kumabana:session-expiree'

export function lireJeton(): string | null {
  try {
    return sessionStorage.getItem(CLE_JETON)
  } catch {
    return null
  }
}

export function ecrireJeton(jeton: string | null): void {
  try {
    if (jeton === null) {
      sessionStorage.removeItem(CLE_JETON)
    } else {
      sessionStorage.setItem(CLE_JETON, jeton)
    }
  } catch {
    // Stockage indisponible : la session ne survivra pas à un rechargement.
  }
}

export class ApiError extends Error {
  status: number
  erreurs: Record<string, string[]>

  constructor(message: string, status: number, erreurs: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.erreurs = erreurs
  }
}

interface Options {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
  corps?: unknown
  signal?: AbortSignal
}

export async function api<T>(chemin: string, options: Options = {}): Promise<T> {
  const jeton = lireJeton()
  const entetes: Record<string, string> = { Accept: 'application/json' }
  if (jeton) entetes.Authorization = `Bearer ${jeton}`
  if (options.corps !== undefined) entetes['Content-Type'] = 'application/json'

  let reponse: Response
  try {
    reponse = await fetch(`/api${chemin}`, {
      method: options.method ?? 'GET',
      headers: entetes,
      body: options.corps === undefined ? undefined : JSON.stringify(options.corps),
      signal: options.signal,
    })
  } catch (e) {
    if (options.signal?.aborted) throw e
    throw new ApiError('Le serveur ne répond pas. Vérifiez votre connexion et réessayez.', 0)
  }

  if (reponse.status === 204) return undefined as T

  let json: unknown = null
  try {
    json = await reponse.json()
  } catch {
    json = null
  }

  if (!reponse.ok) {
    const corps = (json ?? {}) as { message?: string; errors?: Record<string, string[]> }
    if (reponse.status === 401 && jeton) {
      window.dispatchEvent(new Event(EVENEMENT_SESSION_EXPIREE))
    }
    throw new ApiError(
      corps.message ?? 'Une erreur est survenue. Réessayez.',
      reponse.status,
      corps.errors ?? {},
    )
  }

  return json as T
}

export function messageErreur(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Une erreur inattendue est survenue.'
}
