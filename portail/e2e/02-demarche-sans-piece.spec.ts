import { expect, test } from '@playwright/test'
import { AGENT_MAIRIE, panneau, seConnecter, seDeconnecter } from './outils'

/**
 * Le parcours demandé par le défi : une démarche complète sans aucune pièce,
 * puis le citoyen qui retrouve la consultation dans son espace.
 */
test('délivrance d’un document : aucune pièce, puis le citoyen voit la consultation', async ({ page }) => {
  await seConnecter(page, AGENT_MAIRIE)
  await page.goto('/demarches')

  await page.getByRole('radio', { name: /Délivrance d'un document/ }).check()
  await page.getByLabel(/Identifiant du citoyen/).fill('BF-0001')
  await page.getByRole('button', { name: 'Lancer la démarche' }).click()

  // Les deux questions sont à base légale : réponse immédiate, sans accord à attendre
  const zone = panneau(page, 'Résultat')
  await expect(zone.getByText('sur 2')).toBeVisible()
  await expect(zone.getByText('Est majeur')).toBeVisible()
  await expect(zone.getByText('Est vivant')).toBeVisible()
  await expect(zone.getByText('Oui', { exact: true })).toHaveCount(2)

  // Le citoyen retrouve la consultation : qui, quoi, pourquoi, et jamais la donnée
  await seDeconnecter(page)
  await seConnecter(page, 'BF-0001')
  await page.goto('/mon-espace/historique')
  await expect(page.getByRole('heading', { level: 1, name: /Qui a consulté mes données/ })).toBeVisible()
  const consultation = page.getByRole('listitem').filter({ hasText: 'Mairie (démo)' }).filter({ hasText: 'Est vivant' }).first()
  await expect(consultation).toBeVisible()
  await expect(consultation).toContainText("Délivrance d'un document")
  // Ni date de naissance ni fiche du registre dans l'espace du citoyen
  await expect(page.getByText(/19\d\d-\d\d-\d\d/)).toHaveCount(0)
})
