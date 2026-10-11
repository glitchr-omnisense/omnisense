# Omniscient

La vitrine de l'écosystème Omni et la documentation de chaque paquet. Une
plateforme : une seule instance, du code d'app Symfony standard (plan 0,
règle 4), sur glitchr/omnibase, omnibase/admin, omnibase/blog,
omnibase/consent, omnibase/docs et glitchr/typesense-bundle.

Ports de développement : **8124** (HTTP) et **8633** (HTTPS) —
<https://localhost:8633>. La démonstration reste locale.

| Page | Adresse | D'où viennent les données |
|---|---|---|
| Accueil | `/` | les familles trouvées dans les dépôts ; leurs phrases sont des textes du site |
| Catalogue | `/packages`, `/families/{famille}`, `/packages/{vendeur}/{nom}` | `App\Entity\Package`, écrit par `app:packages:sync` |
| Documentation | `/docs`, `/docs/{paquet}/{version}/{page}`, `/docs/_search` | le `docs/` et le README de chaque dépôt (omnibase/docs) ; le `docs/` du site lui-même pour les pages transverses (manuel `glitchr/omniscient`) |
| Installer | `/install` | `App\Catalogue\Installer` : les versions lues par git/git-bundle (`App\Catalogue\Versions`), le `composer.json` de chaque paquet |
| Annonces | `/blog` | omnibase/blog |
| Compte | `/account`, `/login`, `/register` | `App\Entity\Follow` |
| Soutenir | `/support` | `App\Sponsor\GitHubSponsors` |
| Administration | `/admin` | omnibase/admin, en nid |

Voir : [le catalogue](catalogue.md), [les manuels](manuels.md),
[l'identité](identite.md), [ce qui reste à fournir](a-fournir.md).

Ce dossier `developpement/` est la documentation de développement du site ;
le dossier `docs/` est sa documentation publique (Démarrer, Compatibilité,
Conventions, Contribuer), rendue par omnibase/docs comme le manuel de
n'importe quel paquet. `/demos`, qui listait les sites construits sur les
paquets, redirige vers `/docs` : le site ne nomme ni ne compte les sites
(Marco, 2026-10-10).

## Démarrer

```sh
make install-dev            # env, build (composer, yarn), up
make doctrine-migrate
make database-test
docker exec omniscient-web-1 php bin/console doctrine:fixtures:load -n
docker exec omniscient-web-1 php bin/console app:packages:sync     # les dépôts, Packagist, npm
docker exec omniscient-web-1 php bin/console wikidoc:sync          # les autres branches des manuels
docker exec omniscient-web-1 php bin/console wikidoc:index         # l'index Typesense
```

`make down` arrête la pile (sans `-v` : les volumes restent).

Les dépôts des paquets sont montés en lecture seule sous `/srv/repos`
(`docker-compose.override.yml`), et de chaque site seul son `composer.json`
sous `/srv/sites` : rien n'y est écrit. `git/git-bundle` lit ces dépôts par
libgit2 (extension `git2`, compilée dans l'image : `deployments/docker/web/
Dockerfile`), sans url : rien n'est cloné ni rapatrié. Les copies des autres branches vont
dans `var/wikidoc/`.

Comptes des fixtures (développement et tests) : `glitchr` (super-admin),
`omniscient` (admin), `lecteur` (membre) ; le mot de passe est l'identifiant.

## Chaque nuit

`deployments/docker/cron/crontab` (profil `cron`) : `app:packages:sync` à
3 h 30, `wikidoc:index` à 3 h 45.

## Tests

```sh
docker exec omniscient-web-1 php bin/console doctrine:fixtures:load --env=test -n
docker exec omniscient-web-1 php vendor/bin/phpunit
```

Les tests lisent leurs propres dépôts (`tests/Fixtures/repos`,
`tests/Fixtures/sites`), n'appellent personne (`tests/Mock/NoNetwork.php`
répond à la place de Packagist, npm et GitHub) et parlent à une doublure de
Typesense (`tests/Mock/FakeTypesense.php`), qu'un test « éteint » pour
vérifier le repli sur l'index des pages.

Captures : `var/shots/` (bureau et téléphone, clair et sombre).
