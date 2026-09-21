# S.I.S Mobile — Terrain (Expo SDK 54)

App React Native pour **agents / rondiers** (matricule + PIN).

## Stack

| Composant | Version |
|---|---|
| Expo SDK | **54** |
| React Native | **0.81.5** |
| React | **19.1.0** |
| Expo Router | ~6.0 |

Réfs : [Expo docs](https://docs.expo.dev/) · [React Native](https://reactnative.dev/)

## Charte

Alignée sur le back-office : teal `#0F6B5C`, Manrope + IBM Plex Mono, fond papier `#F3F5F4`.

## Démarrage (téléphone physique — QR)

L’API doit écouter sur **toutes** les interfaces (pas seulement localhost) :

```bash
# Terminal 1 — API accessible depuis le Wi‑Fi
cd backend && php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 — Expo
cd sis-mobile && npx expo start
```

Scanne le QR avec **Expo Go** (même Wi‑Fi que le Mac).  
L’app reprend **automatiquement** l’IP LAN d’Expo → `http://IP:8000/api/v1`.

Forcer une IP manuellement :

```bash
echo 'EXPO_PUBLIC_API_URL=http://192.168.x.x:8000/api/v1' > .env
npx expo start
```

### Compte démo

- Matricule : `RD-2001`
- PIN : `1234`

### Simulateur / émulateur

- iOS Simulator → `localhost`
- Android Emulator → `10.0.2.2`

## Fonctionnalités

- Login mobile (matricule + PIN)
- Accueil : mission du jour + file « à vérifier »
- Contrôles de présence (photo + GPS)
- Signalement d’anomalie
- Profil + déconnexion
