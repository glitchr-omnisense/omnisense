# Ce qui reste à fournir

| Quoi | Pourquoi | Où cela se branche |
|---|---|---|
| Un nom de domaine et un hébergeur | le site n'est pas en ligne ; les mentions légales le disent | `.env.prod`, `app.site.host` |
| Un compte GitHub Sponsors pour `glitchr-studio` et un jeton de lecture | sans jeton la page Soutenir dit « Pas de données » et n'affiche rien | `GITHUB_SPONSORS_TOKEN` (`.env.local` ou le coffre) |
| La publication des paquets absents de Packagist | leur fiche dit « Absent de Packagist » et prévient que `composer require` ne les trouvera pas | Packagist ; puis `app:packages:sync --fresh` |
| Les `docs/` des paquets qui n'en ont pas | leur manuel est leur README seul (tuile « Paquets sans documentation » du tableau de bord) | le dépôt de chaque paquet |
| Les adresses publiques des sites | la page Démos pointe les adresses de développement | `App\Catalogue\Sites` |

## Ce qui n'est pas fait

- **La notification à chaque version** : un membre voit sur son compte
  qu'une version a changé ; aucun courriel n'est envoyé.
- **Les onglets de code** (YAML / PHP / attributs) des manuels : les blocs
  se suivent.
- **Les manuels en production** : les dépôts sont lus sur le disque (montés
  en lecture seule en développement). En ligne il faudra les cloner ou en
  télécharger les archives à côté du site.
- **L'administration en anglais** : son menu et ses écrans sont en français.
