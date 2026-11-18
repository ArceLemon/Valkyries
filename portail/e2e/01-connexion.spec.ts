import { expect, test } from '@playwright/test'
import { AGENT_MAIRIE, seConnecter } from './outils'

test('un mauvais mot de passe est refusé', async ({ page }) => {
  await seConnecter(page, AGENT_MAIRIE, 'pas-le-bon')
  await expect(page.getByText('Connexion impossible')).toBeVisible()
  await expect(page).toHaveURL(/\/connexion$/)
})

test("un agent se connecte par e-mail et arrive sur son tableau de bord", async ({ page }) => {
  await seConnecter(page, AGENT_MAIRIE)
  await expect(page).toHaveURL(/\/tableau-de-bord$/)
  await expect(page.getByRole('heading', { level: 1, name: 'Tableau de bord' })).toBeVisible()
})

test('un citoyen se connecte avec son seul identifiant, sans e-mail', async ({ page }) => {
  // En minuscules : le portail le met en forme
  await seConnecter(page, 'bf-0001')
  await expect(page).toHaveURL(/\/mon-espace$/)
  await expect(page.getByRole('heading', { level: 1, name: 'Mon espace' })).toBeVisible()
})
