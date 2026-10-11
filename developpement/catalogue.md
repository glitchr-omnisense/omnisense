# Le catalogue

Rien n'est saisi à la main : `bin/console app:packages:sync` relit chaque
paquet dans son dépôt et écrit `App\Entity\Package`.

```sh
bin/console app:packages:sync             # dépôts + registres
bin/console app:packages:sync --offline   # dépôts seuls : ce que les registres ont dit est gardé
bin/console app:packages:sync --fresh     # redemande aux registres, quel que soit le cache
bin/console app:packages:sync -v          # nomme chaque paquet créé, modifié ou disparu
```

Le même travail se lance depuis l'administration (Paquets › « Relire les
dépôts »). Un verrou (`symfony/lock`) empêche deux passes en même temps.

## Ce qui est lu, et où

| Champ | Source |
|---|---|
| nom, description, licence, mots-clés, ce qu'il requiert | `composer.json`, ou `package.json` pour une bibliothèque JavaScript |
| étage, famille | son nom (`App\Catalogue\Tier::of()`) |
| dépôt, branche | `.git/config` (remote `origin`) et `.git/HEAD` ; à défaut `support.source` / `repository` |
| pages de documentation | les fichiers Markdown de son dossier `docs/` |
| « vérifié en réel » | son README (ci-dessous) |
| sites qui s'en servent | le `composer.json` et le `package.json` de chaque site monté |
| publié, version, téléchargements | Packagist ou npm |

Les dossiers lus sont `OMNI_REPOS/*` et `OMNI_REPOS/*/*`. Un `composer.json`
de type `project` (l'espace de travail d'une famille) n'est pas un paquet ;
un nom qui n'est pas de l'écosystème est ignoré. Un paquet dont le dépôt a
disparu est marqué introuvable et quitte les pages ; il n'est pas supprimé.

## Les étages

| Étage | Nom |
|---|---|
| Socle | `glitchr/omnibase` |
| Métier | `omnibase/<métier>` |
| Famille | `glitchr/omni<question>` |
| Fournisseur | `omni<question>/<service>` |
| Bibliothèque | `@glitchr/<nom>` (npm) |

Une famille **existe** quand son paquet a été trouvé dans un dépôt. Les
noms seulement annoncés (`App\Catalogue\Families::ANNOUNCED` : prévus ou
réservés au plan 0) sont listés à part au bas du catalogue, sans lien ni
fiche, et leur adresse `/families/<nom>` répond 404. L'ordre dans lequel le
suffixe tourne à l'accueil est `Families::ORDER` ; la phrase de chaque
famille est un texte du site (`families.<nom>.phrase`).

## Les registres

`App\Catalogue\PackageRegistries` interroge les API publiques, sans clé :
`packagist.org/packages/<nom>.json`, `registry.npmjs.org/<nom>` et le
compteur de téléchargements de npm. Trois réponses possibles :

- **publié** (avec sa version la plus récente : une étiquette s'il y en a
  une, sinon la plus haute branche de développement) ;
- **absent** (le registre a répondu 404) ;
- **pas de réponse** (hors ligne, délai, erreur) : rien n'est écrit, ce qui
  était connu est gardé avec sa date ; un paquet jamais interrogé s'affiche
  « n'a pas été interrogé », pas « absent ».

Une réponse est gardée douze heures (`cache.app`) ; un silence n'est pas
mis en cache.

## « Vérifié en réel »

L'état vient du README du paquet (`App\Catalogue\Verification::read()`),
jamais d'une supposition :

| État | Quand |
|---|---|
| vérifié | le README le dit (« Verified against the real service on… ») |
| non vérifié | il dit que non (« Unverified… », « Not verified against… ») |
| en partie | il dit les deux, ou sa réserve est écrite sous une section à elle |
| (rien) | il n'en dit rien |

Les lignes de tableau et les blocs de code sont ignorés : le tableau des
fournisseurs d'une famille ne parle pas de la famille. La phrase lue est
gardée et citée sur la fiche du paquet (« Extrait de son README »).

C'est une lecture de texte, avec ses limites : « en partie » repose sur la
place de la phrase dans le README, et un README qui dirait la chose
autrement ne serait pas reconnu. La fiche montre toujours la phrase d'origine
pour que le lecteur juge.

## Suivre un paquet

Un membre connecté suit un paquet depuis sa fiche (`App\Entity\Follow`) ;
`/account` liste ses paquets avec la version que sert le registre
aujourd'hui, marquée quand elle n'est plus celle du jour où il a commencé à
suivre. Aucun courriel n'est envoyé pour l'instant.
