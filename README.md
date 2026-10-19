# Page Lock Manager

Plugin WordPress maison (Maxev) pour protéger des pages par mot de passe,
avec un panneau intégré à l'éditeur.

- Mot de passe hashé, cookie d'accès par page, limite d'essais.
- S'appuie sur la protection native de WordPress : respecté par Elementor,
  l'API REST, les flux RSS et la recherche.
- Mis à jour automatiquement depuis les releases GitHub.

## Utilisation

Éditer une page → panneau **Page Lock Manager** dans la barre latérale (ou
metabox dans l'éditeur classique) → activer, saisir ou générer un mot de passe
→ enregistrer. La liste des pages affiche une colonne « Protection ».

Les utilisateurs pouvant éditer la page la voient toujours sans mot de passe :
tester en navigation privée.

## Développement

```bash
npm install
npm run start     # build en mode watch
npm run build     # build de production
npm run lint:js
npm run package   # ZIP installable dans dist/
```

Lire [AGENTS.md](AGENTS.md) avant toute modification. Architecture :
[ARCHITECTURE.md](ARCHITECTURE.md). Releases : [RELEASE.md](RELEASE.md).

## Licence

GPL-2.0-or-later.
