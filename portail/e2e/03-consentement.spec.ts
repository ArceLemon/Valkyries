import { expect, test } from '@playwright/test'
import { AGENT_COMMERCE, panneau, seConnecter, seDeconnecter } from './outils'

/**
 * Création d'entreprise : le casier est une donnée sensible. Le registre n'est
 * interrogé qu'après l'accord du citoyen, donné depuis son espace.
 */
test('création d’entreprise : le casier attend l’accord du citoyen', async ({ page }) => {
  await seConnecter(page, AGENT_COMMERCE)
  await page.goto('/demarches')
  await page.getByRole('radio', { name: /Création d'entreprise/ }).check()
  await page.getByLabel(/Identifiant du citoyen/).fill('BF-0001')
  await page.getByRole('button', { name: 'Lancer la démarche' }).click()

  const zone = panneau(page, 'Résultat')
  await expect(zone.getByText('sur 3')).toBeVisible()
  const casier = zone.getByRole('listitem').filter({ hasText: 'Casier judiciaire vierge' })
  await expect(casier).toContainText('En attente du citoyen')
  const detail = await casier.getByRole('link', { name: 'Voir le détail' }).getAttribute('href')
  expect(detail).toBeTruthy()

  // Le citoyen voit qui demande, quoi et pourquoi, puis accepte
  await seDeconnecter(page)
  await seConnecter(page, 'BF-0001')
  await page.goto('/mon-espace/a-valider')
  await expect(page.getByRole('heading', { level: 1, name: 'Demandes à valider' })).toBeVisible()
  const demande = page.getByRole('listitem').filter({ hasText: 'Casier judiciaire vierge' }).first()
  await expect(demande).toContainText('Registre du commerce (démo)')
  await expect(demande).toContainText("Immatriculation d'une entreprise")
  await expect(demande).toContainText('Seule la réponse oui ou non sera transmise')

  // Accepte toutes les demandes en attente (y compris celles d'un essai précédent)
  const accepter = page.getByRole('button', { name: 'Accepter' })
  while ((await accepter.count()) > 0) {
    await accepter.first().click()
    await expect(page.getByText('Accord validé').first()).toBeVisible()
    await page.reload()
    await expect(page.getByRole('heading', { level: 1, name: 'Demandes à valider' })).toBeVisible()
    await page.waitForLoadState('networkidle')
  }
  await expect(page.getByText('Aucune demande en attente')).toBeVisible()

  // Côté agent, la réponse est arrivée avec son attestation signée
  await seDeconnecter(page)
  await seConnecter(page, AGENT_COMMERCE)
  await expect(async () => {
    await page.goto(detail!)
    await expect(page.getByRole('heading', { level: 1, name: 'Casier judiciaire vierge' })).toBeVisible()
    await expect(panneau(page, 'Réponse')).toContainText('Oui', { timeout: 2_000 })
  }).toPass({ timeout: 30_000 })
  await expect(page.getByRole('heading', { name: 'Attestation signée' })).toBeVisible()
})
