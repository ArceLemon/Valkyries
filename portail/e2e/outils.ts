import { expect, type APIRequestContext, type Page } from '@playwright/test'

/** Mot de passe de tous les comptes de démonstration (fictifs). */
export const MOT_DE_PASSE = 'kumabana-demo'

export const AGENT_MAIRIE = 'agent.mairie@demo.kumabana.test'
export const AGENT_COMMERCE = 'agent.commerce@demo.kumabana.test'
export const AUDITEUR = 'auditeur@demo.kumabana.test'

/** Connexion par le formulaire : un e-mail pour les agents, l'identifiant pour un citoyen. */
export async function seConnecter(page: Page, login: string, motDePasse = MOT_DE_PASSE) {
  await page.goto('/connexion')
  await page.getByLabel(/Adresse e-mail ou identifiant/).fill(login)
  await page.locator('input[name="mot_de_passe"]').fill(motDePasse)
  await page.getByRole('button', { name: 'Se connecter' }).click()
  // On attend la fin de la connexion avant d'aller ailleurs (sinon le jeton n'est pas encore là)
  if (motDePasse === MOT_DE_PASSE) await expect(page).not.toHaveURL(/\/connexion$/)
}

export async function seDeconnecter(page: Page) {
  // Le portail garde le jeton dans le stockage de l'onglet
  await page.evaluate(() => {
    window.sessionStorage.clear()
    window.localStorage.clear()
  })
  await page.context().clearCookies()
}

/** Un panneau de la page, retrouvé par son titre. */
export function panneau(page: Page, titre: string) {
  return page.locator('section', { has: page.getByRole('heading', { name: titre, exact: true }) })
}

/** Jeton d'API pour préparer des données sans passer par l'écran. */
export async function jeton(request: APIRequestContext, email: string): Promise<string> {
  const reponse = await request.post('/api/auth/connexion', {
    data: { email, mot_de_passe: MOT_DE_PASSE },
  })
  expect(reponse.ok()).toBeTruthy()
  return (await reponse.json()).jeton as string
}
