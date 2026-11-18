# KUMA BANA : portail web

## Tests de bout en bout

Huit parcours Playwright (`e2e/`) jouent le portail dans le Chrome installé sur la machine, contre le système lancé par `docker compose up` (port 5173) :

```powershell
npm run e2e                                         # fenêtre visible, au ralenti
$env:SANS_FENETRE=1; $env:RALENTI=0; npm run e2e    # sans fenêtre, à pleine vitesse
```

Ils utilisent les comptes de démonstration et ajoutent des demandes à l'historique d'Ousmane (BF-0001). Fatimata (BF-0005) et KABORE (BF-0004) ne sont pas touchés, pour la démonstration.
