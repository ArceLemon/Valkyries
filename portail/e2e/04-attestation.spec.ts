import { expect, test } from '@playwright/test'
import { AGENT_MAIRIE, jeton } from './outils'

/** N'importe qui vérifie une attestation, sans compte ; une seule lettre changée et elle est refusée. */
test('une attestation se vérifie sans compte et ne se falsifie pas', async ({ page, request }) => {
  // Préparation par l'API : la mairie obtient une réponse signée pour BF-0001
  const cle = await jeton(request, AGENT_MAIRIE)
  const entetes = { Authorization: `Bearer ${cle}`, Accept: 'application/json' }
  const lot = await request.post('/api/demarches/delivrance-document/lancer', {
    headers: entetes,
    data: { citoyen: { identifiant: 'BF-0001' } },
  })
  expect(lot.ok()).toBeTruthy()
  const reference = ((await lot.json()).demandes as { reference?: string; reponse?: string }[])
    .find((d) => d.reponse === 'oui' && d.reference)?.reference
  expect(reference).toBeTruthy()
  const demande = await (await request.get(`/api/demandes/${reference}`, { headers: entetes })).json()
  const attestation = demande.attestation as string
  expect(attestation).toContain('.')

  // Page publique, sans connexion
  await page.goto('/verifier')
  await expect(page.getByRole('heading', { level: 1, name: 'Vérifier une attestation' })).toBeVisible()
  const champ = page.getByLabel(/Attestation/)
  const verifier = page.getByRole('button', { name: 'Vérifier', exact: true })

  await champ.fill(attestation)
  await verifier.click()
  await expect(page.getByText('Attestation valide')).toBeVisible()
  await expect(page.getByText('Mairie (démo)')).toBeVisible()
  await expect(page.getByText('BF-0001')).toHaveCount(0)

  // Une seule lettre modifiée au milieu de la signature
  const i = attestation.length - 10
  const remplacement = attestation[i] === 'A' ? 'B' : 'A'
  await champ.fill(attestation.slice(0, i) + remplacement + attestation.slice(i + 1))
  await verifier.click()
  await expect(page.getByText('Attestation modifiée ou illisible')).toBeVisible()
  await expect(page.getByText("Ce que l'attestation affirme")).toHaveCount(0)
})
