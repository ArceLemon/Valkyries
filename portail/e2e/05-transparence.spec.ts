import { expect, test } from '@playwright/test'
import { AUDITEUR, seConnecter } from './outils'

test('le catalogue des questions est public', async ({ page }) => {
  await page.goto('/catalogue')
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await expect(page.getByText('Casier judiciaire vierge').first()).toBeVisible()
  await expect(page.getByText('Est majeur').first()).toBeVisible()
})

test("l'auditeur vérifie que la chaîne du journal est intacte", async ({ page }) => {
  await seConnecter(page, AUDITEUR)
  await expect(page).toHaveURL(/\/journal$/)
  await expect(page.getByRole('heading', { level: 1, name: 'Journal et intégrité' })).toBeVisible()
  await page.getByRole('button', { name: 'Intégrité' }).click()
  await page.getByRole('button', { name: 'Vérifier la chaîne' }).click()
  await expect(page.getByText('Journal intègre')).toBeVisible()
})
