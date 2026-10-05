# L'identité

Une feuille de plan. Le site présente un système en étages : son dessin est
celui d'un plan d'architecte, pas d'une page de produit.

- **Papier et encre** : un film à dessin gris-bleu très clair (`#f3f5f6`),
  une encre noire (`#0f1419`), des filets à la place des boîtes. La nuit, le
  bleu-noir d'un cyanotype et son trait blanc.
- **Une seule couleur** : le bleu d'un tirage de plan (`#1d3fd8`, `#93adff`
  la nuit), pour ce sur quoi on agit et pour le suffixe qui tourne après
  « Omni ». Trois signaux discrets pour l'état d'un paquet (publié, en
  partie, absent).
- **Deux caractères** : Archivo, dessiné large (axe de largeur à 125 %),
  pour la marque et les titres ; Recursive pour ce qui se lit, et le même
  caractère en chasse fixe (axe `MONO`) pour le code, les noms de paquets et
  les cotes.
- **La marque** : « Omni » en gras, le suffixe en maigre et en bleu —
  `Omni|scient` dans l'en-tête, `Omni|base`, `Omni|trade`… ailleurs. À
  l'accueil le suffixe est écrit sur un blanc souligné, comme une case à
  remplir (`assets/controllers/suffix_controller.js` ; sans mouvement quand
  le visiteur le demande).
- **La coupe** : les quatre étages sont dessinés comme une coupe de
  bâtiment, chacun avec la hachure de son matériau ; deux bâtiments, parce
  que les familles ne reposent pas sur le socle.
- **Les cotes** : les petites étiquettes en capitales à chasse fixe
  (`.om-cote`) numérotent les sections et nomment les champs.

Ce qui n'y est pas : pas de dégradé, pas de rangée de cartes à icône, pas de
coins arrondis (2 px), pas d'ombre portée.

La signature Glitch Art du pied de page est le partiel du socle
(`@Base/partials/_credits.html.twig`), noire le jour, blanche la nuit,
jamais teintée.

Fichiers : `assets/styles/_tokens.scss` (palette, caractères),
`app-async.scss` (premier rendu, variables lues par les bundles),
`layout1.scss` (cadre et pages), `_bundles.scss` (les pages des bundles
ramenées à la feuille).
