---
title: Écrire un provider
description: Du template à la publication - implémenter le contrat d'une famille, passer sa suite de conformité, documenter ce qui a été vérifié, publier une version.
order: 6
---

# Écrire un provider

Un provider est un paquet à part entière - `omnimap/maplibre`, `omnibus/colissimo`,
`omnidrive/s3` - qui implémente le contrat d'une famille pour un service. Tous s'écrivent, se
testent, se documentent et se publient de la même façon ; ce qui change d'une famille à l'autre,
c'est le contrat et sa suite de conformité.

## Partir du template

[glitchr-studio/omni-provider-template](https://github.com/glitchr-studio/omni-provider-template)
est un dépôt modèle GitHub, avec un squelette par famille. Un script remplit les noms :

```sh
gh repo create <owner>/omnimap-maplibre --public --template glitchr-studio/omni-provider-template --clone
cd omnimap-maplibre
bin/init omnimap maplibre "MapLibre"
composer install && vendor/bin/phpunit
```

La suite est verte avant la première ligne écrite : chaque squelette est le plus petit provider qui
tienne le contrat de sa famille. On obtient :

- `composer.json` : le nom du paquet, le cœur de la famille, aucun framework dans `require` ;
- la factory et le provider, qui implémentent le contrat de la famille ;
- `Tests/<Name>ContractTest.php` : la suite de conformité de la famille, branchée ;
- `Tests/BareTest.php` : chaque classe chargée avec les seules dépendances du paquet ;
- `README.md` et sa section **Verified, and not**, `docs/index.md`, `CHANGELOG.md`, la `LICENSE` MIT ;
- `.github/workflows/ci.yml` et `release.yml`, qui appellent
  [glitchr-studio/ci](https://github.com/glitchr-studio/ci) épinglé à un commit.

## Les suites de conformité

Chaque cœur de famille livre sa suite sous forme de trait PHPUnit dans `<Core>\Testing`. La classe
de test d'un provider l'utilise et lui donne le provider, construit sur des réponses enregistrées :
la suite n'atteint jamais le réseau.

| Famille | Suite | Ce qu'elle exige de chaque provider |
|---|---|---|
| omnimap | `Omnimap\Testing\ProviderContractTests` | factory et registre, capacités conformes, description du rendu sur des hôtes déclarés, images, tuiles, géocodage, règle d'erreurs |
| omnidrive | `Omnidrive\Testing\DriveContractTests` | écrire, lire, flux, lister, déplacer, visibilité, fichier introuvable |
| omnibus | `Omnibus\Testing\GatewayContractTests` | chaque requête prise en charge ou refusée, tarifs, étiquettes, suivi, points relais, erreurs du transporteur |
| omnitrade | `Omnitrade\Testing\GatewayContractTests` | montants en unités mineures, statuts, remboursements, annulations, notifications signées, erreurs |
| omnistate | `Omnistate\Testing\RegistryContractTests` | chaque contrat de registre servi ou refusé, 404 rendu en null, indisponibilité et délai avant nouvel essai |
| omnishield | `Omnishield\Testing\GatewayContractTests` | capacités comme interfaces, verdicts, scores, erreurs de clé et de joignabilité |
| omnisearch | `Omnisearch\Testing\DriverContractTests` | index, mots, filtres, tris, pages, facettes, écritures, moteur qui ne répond pas |
| omniauth | `Omniauth\Testing\ProviderContractTests` | l'URL d'autorisation (PKCE, state, nonce), l'échange du code, les refus sans secret |

Une vérification que le service ne peut légitimement pas tenir devient un point d'extension
optionnel du trait, documenté - jamais un test sauté. Une vérification qu'il échoue est un défaut,
corrigé dans le provider.

## La règle d'erreurs

Elle est la même dans chaque famille. Les refus du service (401, 403, 404, 429, 5xx) et ses
réponses illisibles (la page HTML d'un proxy en 200) donnent l'exception provider de la famille,
avec le message du service. Une requête restée sans réponse donne son exception « injoignable ».
Une capacité que le provider n'a pas donne son exception « non pris en charge » - jamais une
`TypeError`, jamais un `null` silencieux. Le `docs/providers.md` de chaque cœur nomme ses
exceptions.

## Documenter ce qui a été vérifié

La section **Verified, and not** du README dit ce qui a tourné - la suite, hors ligne ; les
vérifications en réel, à quelle date, contre quoi - et ce qui n'a pas tourné. Le catalogue la lit :
la page du paquet et le [tableau de compatibilité](/fr/compatibilite) affichent « vérifié en
réel », « en partie » ou « non vérifié » d'après ces mots, jamais par supposition.

## Publier

1. Déplacer les entrées `## Unreleased` du changelog sous `## [X.Y.Z] - AAAA-MM-JJ`, pousser,
   attendre une CI verte.
2. `git tag -a vX.Y.Z -m X.Y.Z && git push origin vX.Y.Z`.
3. Le workflow de release relance la suite sur le tag et prend les notes dans le changelog : sans
   section, pas de release. Il joint l'archive source du tag avec son SHA-256 et une attestation de
   provenance, puis publie la release GitHub - en pré-release pour un tag suffixé ou toute 0.x.
4. Packagist : le dépôt est soumis une fois. Ensuite, son intégration GitHub prend les tags, ou bien
   le workflow les lui signale quand le dépôt a les secrets `PACKAGIST_USERNAME` et
   `PACKAGIST_TOKEN`.

Un tag publié n'est jamais déplacé ni supprimé : une erreur se corrige par une nouvelle version.

## Le tableau de compatibilité

[/fr/compatibilite](/fr/compatibilite) est généré à chaque construction du site, depuis GitHub.
Pour chaque paquet, il montre :

- les jobs de sa dernière CI sur sa branche de développement ;
- si ses tests passent la suite de conformité de sa famille, et si cette CI est verte ;
- ce que son README dit d'une vérification en réel.
