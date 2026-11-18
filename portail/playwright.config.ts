import { defineConfig } from '@playwright/test'

/**
 * Tests de bout en bout dans le vrai Chrome installé sur la machine (aucun navigateur
 * n'est téléchargé). Les services doivent tourner : voir dev.ps1 à la racine.
 *
 *   npm run e2e                                       fenêtre visible, au ralenti, pour regarder
 *   $env:SANS_FENETRE=1; $env:RALENTI=0; npm run e2e  sans fenêtre, à pleine vitesse
 */
const ralenti = Number(process.env.RALENTI ?? 400)

export default defineConfig({
  testDir: './e2e',
  // Les scénarios partagent les mêmes données de démonstration : ils s'exécutent dans l'ordre.
  fullyParallel: false,
  workers: 1,
  timeout: 120_000,
  expect: { timeout: 15_000 },
  reporter: [['list']],
  use: {
    baseURL: process.env.URL_PORTAIL ?? 'http://127.0.0.1:5173',
    channel: 'chrome',
    headless: process.env.SANS_FENETRE === '1',
    viewport: { width: 1280, height: 860 },
    locale: 'fr-FR',
    colorScheme: 'light',
    launchOptions: { slowMo: ralenti },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
})
