# Installer et tester l'application mobile KUMA BANA

L'application mobile est l'espace du citoyen : demandes à valider, accords, historique des consultations, attestations avec code QR et notifications.

Elle ne contient aucune donnée. Elle parle au serveur KUMA BANA qui tourne dans Docker sur votre ordinateur. **L'adresse de ce serveur est fixée au moment où l'APK est construit.** Il faut donc construire l'APK avec l'adresse de votre ordinateur (étape 3) ; un APK construit sur une autre machine ne trouvera pas votre serveur.

Durée : environ 15 minutes la première fois, surtout pour les téléchargements.

---

## Ce qu'il faut

| Besoin | Détail |
|---|---|
| Docker Desktop | Pour le serveur et, si vous n'avez pas Flutter, pour construire l'APK |
| Un téléphone Android 7 ou plus récent | Ou un émulateur Android (voir la fin du document) |
| Le même réseau Wi-Fi | Le téléphone et l'ordinateur doivent être sur le même réseau |
| Environ 8 Go d'espace disque | Images Docker et outils Android |

Il n'est **pas** nécessaire d'installer Flutter ni Android Studio : l'étape 3 peut tout faire dans Docker.

---

## 1. Démarrer le serveur

Depuis le dossier du projet :

```powershell
docker compose up --build -d
docker compose ps
```

Attendez que `api` et `postgres` soient indiqués `healthy`. Le portail web est alors ouvert sur http://localhost:5173.

## 2. Trouver l'adresse de l'ordinateur et ouvrir le port

**Adresse IP** :

- Windows : `ipconfig`, ligne « Adresse IPv4 » de la carte Wi-Fi (par exemple `192.168.1.20`) ;
- macOS : `ipconfig getifaddr en0` ;
- Linux : `hostname -I`.

**Pare-feu** (Windows, dans PowerShell ouvert en administrateur) :

```powershell
New-NetFirewallRule -DisplayName "KUMA BANA 5173" -Direction Inbound -Protocol TCP -LocalPort 5173 -Action Allow
```

**Vérification** : sur le navigateur du téléphone, ouvrez `http://<adresse IP>:5173`. Si le portail KUMA BANA s'affiche, le réseau est prêt. S'il ne s'affiche pas, l'application ne pourra pas se connecter non plus : revoyez le Wi-Fi et le pare-feu.

Seul le port 5173 est utilisé. Il relaie l'API (`/api`) et les notifications push (`/notifications`).

## 3. Construire l'APK avec votre adresse

Remplacez `adresse_IP` par votre adresse IP dans la commande choisie(entre l'option A ou B).

### Option A : sans Flutter, avec Docker (recommandée)

Depuis le dossier du projet, dans PowerShell :

```powershell
docker run --rm -v "${PWD}\mobile:/src:ro" -v "${PWD}:/out" ghcr.io/cirruslabs/flutter:stable sh -c "git config --global --add safe.directory '*' && cd /sdks/flutter && git fetch --depth 1 origin tag 3.50.0 && git checkout -q 3.50.0 && mkdir -p /work && cd /src && tar --exclude=./build --exclude=./.dart_tool --exclude=./android/.gradle --exclude=./android/local.properties -cf - . | (cd /work && tar xf -) && cd /work && flutter pub get && flutter build apk --release --dart-define=API_URL=http://adresse_IP:5173/api && cp build/app/outputs/flutter-apk/app-release.apk /out/KumaBana.apk"
```

Sous macOS ou Linux, remplacez `"${PWD}\mobile:/src:ro"` par `"$(pwd)/mobile:/src:ro"` et `"${PWD}:/out"` par `"$(pwd):/out"`.

La commande :

1. télécharge une image contenant Flutter et le SDK Android, environ 3 Go ;
2. passe Flutter à la version 3.50.0, celle du projet ;
3. construit l'APK sur une copie du dossier `mobile`, sans rien modifier dans vos fichiers.

Elle dépose `KumaBana.apk` dans le dossier du projet. Comptez 10 à 20 minutes la première fois.

### Option B : avec Flutter déjà installé

Il faut Flutter 3.50 ou plus récent (Dart 3.14) et le SDK Android. Vérifiez avec `flutter doctor`, puis :

```powershell
cd mobile
flutter pub get
flutter build apk --release --dart-define=API_URL=http://adresse_IP:5173/api
```

L'APK est dans `mobile/build/app/outputs/flutter-apk/app-release.apk`.

## 4. Installer l'APK sur le téléphone

1. Copiez `KumaBana.apk` sur le téléphone (câble USB, messagerie, cloud…). Avec le débogage USB activé, vous pouvez aussi lancer `adb install KumaBana.apk`.
2. Ouvrez le fichier sur le téléphone. Android demande d'autoriser l'installation depuis cette source : acceptez.
3. Après la première connexion, acceptez les notifications : c'est par elles que le citoyen est prévenu d'une nouvelle demande.

Si une ancienne version de KUMA BANA est installée et que l'installation est refusée, désinstallez-la d'abord.

## 5. Se connecter

Toutes les données sont fictives.

| Compte | Identifiant | Mot de passe |
|---|---|---|
| Ousmane Ilboudo | `BF-0001` | `kumabana-demo` |
| Aminata Sawadogo | `BF-0003` | `kumabana-demo` |
| Fatimata Ouedraogo | `BF-0005` | `kumabana-demo` |

**Créer un compte** : choisissez « Créer mon compte » et saisissez les données ci-dessous, avec le mot de passe de votre choix. L'inscription n'aboutit que si l'identité correspond à l'état civil.

| Champ | Valeur |
|---|---|
| Identifiant | `BF-0004` |
| Nom | `KABORE` |
| Prénoms | `Issouf` |
| Téléphone | `70 00 00 04` |

**Voir une demande arriver sur le téléphone** :

1. Connectez-vous sur l'application avec `BF-0001`.
2. Sur le portail (http://localhost:5173), connectez-vous comme agent avec `agent.commerce@demo.kumabana.test` / `kumabana-demo`.
3. Lancez la démarche « Création d'entreprise » pour `BF-0001`.
4. La question sur le casier judiciaire attend l'accord du citoyen : une notification arrive sur le téléphone, et la demande apparaît dans l'onglet « À valider ».

## Sans téléphone : l'émulateur Android

Dans Android Studio, créez et démarrez un émulateur. Construisez ensuite l'APK (étape 3) avec l'adresse `http://10.0.2.2:5173/api`, qui désigne l'ordinateur vu depuis l'émulateur. Installez l'APK en le glissant dans la fenêtre de l'émulateur. Les étapes 2 (adresse IP et pare-feu) ne sont pas nécessaires.

## En cas de problème

| Symptôme | Cause probable et solution |
|---|---|
| « Impossible de joindre le serveur » dans l'application | Le téléphone n'atteint pas l'ordinateur. Faites la vérification de l'étape 2 dans le navigateur du téléphone : même Wi-Fi, pare-feu ouvert, serveur démarré. Un VPN actif sur l'ordinateur peut aussi bloquer : coupez-le. |

| Ça marchait, puis plus rien | L'adresse IP de l'ordinateur a changé (autre réseau Wi-Fi). Refaites l'étape 3 avec la nouvelle adresse. |

| Pas de notification | Autorisez les notifications de KUMA BANA dans les réglages Android et désactivez l'optimisation de batterie pour l'application. |

| La construction Docker échoue en téléchargeant (« Error reading Zip », « Could not GET ») | Connexion coupée pendant un téléchargement. Relancez la même commande. |

| Windows refuse `dart.exe` (« Device Guard ») | Smart App Control bloque Flutter installé localement. Utilisez l'option A (Docker). |
