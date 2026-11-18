# KUMA BANA : application citoyenne

Application Flutter : demandes à valider, accords, historique des consultations, attestations avec code QR.

Pour installer l'application sur un téléphone, y compris sans Flutter, suivez le guide pas à pas : [docs/INSTALLATION_MOBILE.md](../docs/INSTALLATION_MOBILE.md).

```powershell
flutter pub get
flutter run                                              # l'émulateur Android joint l'API via 10.0.2.2
flutter build apk --release --dart-define=API_URL=http://192.168.1.20:5173/api   # téléphone réel : adresse de l'ordinateur
# Le port 5173 (portail Docker) relaie l'API et les notifications push (ntfy) : un seul port à ouvrir.
flutter test
```

Le logo est dans `assets/` ; la police Geist (licence OFL) est dans `assets/fonts/`.
