---
name: Gachamélia — backoffice
description: Identité crème, encre et rose, en-tête sombre et surfaces arrondies dans le backoffice.
colors:
  rose: "#c41649"
  dark-rose: "#ff86ab"
  pink: "#e97896"
  dark-pink: "#ef9bad"
  gold: "#d4a13f"
  dark-gold: "#dfb65e"
  gold-ink: "#7a5415"
  dark-gold-ink: "#f0c878"
  leaf: "#2f594e"
  dark-leaf: "#94c7b5"
  cream: "#fbf3ea"
  dark-cream: "#161513"
  ivory: "#fffaf4"
  dark-ivory: "#201f1c"
  surface: "#fff"
  dark-surface: "#24231f"
  ink: "#241813"
  dark-ink: "#ece9e5"
  muted: "#66544c"
  dark-muted: "#b8b4ae"
  blush: "#f5ded6"
  dark-blush: "#35272b"
  on-strong: "#fff"
  dark-on-strong: "#161513"
  contrast-bg: "#241813"
  dark-contrast-bg: "#0b0b0a"
  contrast-fg: "#fffaf4"
  dark-contrast-fg: "#ece9e5"
typography:
  headline:
    fontFamily: "Fraunces, Georgia, serif"
    fontSize: "1.75rem"
    fontWeight: 800
    lineHeight: 1.25
  headline-wide:
    fontFamily: "Fraunces, Georgia, serif"
    fontSize: "2rem"
    fontWeight: 800
    lineHeight: 1.25
  title:
    fontFamily: "Fraunces, Georgia, serif"
    fontSize: "1.25rem"
    fontWeight: 800
  card-heading:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 800
  body:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    lineHeight: "1.5rem"
  label:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 700
  lead:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.625
  lead-large:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 500
    lineHeight: 1.625
  button:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8rem"
    fontWeight: 800
rounded:
  panel: "1rem"
  soft: "0.75rem"
  control: "0.6rem"
  pill: "999px"
  avatar: "50%"
spacing:
  quarter: "0.25rem"
  half: "0.5rem"
  three-quarter: "0.75rem"
  unit: "1rem"
  card: "1.25rem"
  panel: "1.5rem"
  section: "2rem"
  page-top: "1.75rem"
  page-bottom: "3rem"
components:
  button-ink:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.on-strong}"
    typography: "{typography.button}"
    rounded: "{rounded.pill}"
    padding: "0 1rem"
  button-ink-hover:
    backgroundColor: "{colors.rose}"
  button-rose:
    backgroundColor: "{colors.rose}"
    textColor: "{colors.on-strong}"
    typography: "{typography.button}"
    rounded: "{rounded.pill}"
    padding: "0 1rem"
  button-leaf:
    backgroundColor: "{colors.leaf}"
    textColor: "{colors.on-strong}"
    typography: "{typography.button}"
    rounded: "{rounded.pill}"
    padding: "0 1rem"
  button-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.pill}"
    padding: "0 1rem"
  button-danger:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.rose}"
    typography: "{typography.button}"
    rounded: "{rounded.pill}"
    padding: "0 1.25rem"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0 0.75rem"
  navigation-active:
    backgroundColor: "{colors.contrast-bg}"
    textColor: "{colors.contrast-fg}"
    rounded: "{rounded.control}"
    padding: "0 0.75rem"
  badge-rose:
    backgroundColor: "{colors.blush}"
    textColor: "{colors.rose}"
    rounded: "{rounded.pill}"
    padding: "0 0.75rem"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.panel}"
    padding: "1.25rem"
  panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.panel}"
  template-card:
    backgroundColor: "{colors.ivory}"
    rounded: "{rounded.panel}"
    padding: "1.5rem"
  template-mark:
    backgroundColor: "{colors.blush}"
    textColor: "{colors.rose}"
    rounded: "{rounded.soft}"
    width: "3.5rem"
    height: "3.5rem"
  header-action:
    backgroundColor: "transparent"
    textColor: "{colors.contrast-fg}"
    typography: "{typography.button}"
    rounded: "{rounded.pill}"
    padding: "0 1rem"
  theme-toggle:
    backgroundColor: "transparent"
    textColor: "{colors.contrast-fg}"
    rounded: "{rounded.pill}"
    padding: "0"
    width: "2.75rem"
---

# Design System: Gachamélia — backoffice

## Overview

**Creative North Star: "Identité crème, encre et rose"**

Ce document décrit l’identité conservée et son rapprochement visuel validé avec
le backoffice Kiss-Shot : en-tête sombre, titres compacts et surfaces adoucies.
Le socle partagé reste celui de `assets/styles/base.css` ; les composants et les
substitutions du thème sont définis dans `assets/styles/backoffice.css`.

Les titres Fraunces conservent leur caractère éditorial, tandis que DM Sans porte
les contrôles et les données. L’interface privilégie les panneaux arrondis,
les boutons en pilule et les repères rose. La variante sombre conserve ces formes,
ces espacements et les rôles de couleur ; elle modifie leurs valeurs uniquement.

**Key Characteristics:**
- Crème et ivoire en mode clair ; surfaces chaudes sombres dans le backoffice.
- Titres Fraunces, textes et contrôles DM Sans.
- En-tête sombre dans les deux thèmes, navigation active contrastée.
- Panneaux arrondis, petites surfaces douces, boutons et badges en pilule.
- Catégories identifiées par un SVG accompagné d’un libellé.
- Couleurs sémantiques partagées par les composants des deux thèmes.

Périmètre : backoffice uniquement. `PRODUCT.md` reste la vérité produit. La
source finale et les captures desktop de la vue d’ensemble claire et des stats
de rôle sombres ont été inspectées pour cette synchronisation. Les captures
locales authentifiées de Kiss-Shot servent de référence de caractère visuel ;
aucune maquette approuvée ni obligation de reproduction pixel à pixel n’existe.
Le packet de revue consigne les essais desktop/mobile, clavier et rechargement.
La préférence OS sombre et la réduction de mouvement restent vérifiées en
source uniquement ; cette synchronisation ne répète pas ces essais en navigateur.

## Colors

Les valeurs du frontmatter correspondent exactement aux déclarations CSS. Les
clés sans préfixe décrivent le mode clair ; les clés `dark-` décrivent les mêmes
rôles en mode sombre. Il existe quatorze rôles de couleur, chacun avec deux valeurs.
Les composants référencent le rôle CSS actif, jamais une palette copiée localement.

### Primary

- **Rose** (`rose`, `dark-rose`) : actions, liens, icônes, badge Backoffice, erreurs,
  curseur de saisie et cases à cocher.
- **Rose doux** (`pink`, `dark-pink`) : accents secondaires de la même famille.

### Secondary

- **Feuille** (`leaf`, `dark-leaf`) : publication, succès et état valide.
- **Or** (`gold`, `dark-gold`) : compteurs et avertissements.
- **Encre d’or** (`gold-ink`, `dark-gold-ink`) : texte des badges dorés ; son
  rôle est distinct du fond doré translucide.

### Neutral

- **Crème** (`cream`, `dark-cream`) : fond de page et nouvelles lignes.
- **Ivoire** (`ivory`, `dark-ivory`) : cartes de catégories, identité de serveur,
  barres d’actions et en-têtes de tableau.
- **Surface** (`surface`, `dark-surface`) : panneaux, champs, cartes et boutons contour.
- **Encre** (`ink`, `dark-ink`) : texte principal et actions fortes.
- **Texte discret** (`muted`, `dark-muted`) : indications et libellés secondaires.
- **Blush** (`blush`, `dark-blush`) : sélection, erreurs et accents doux.
- **Texte sur fond fort** (`on-strong`, `dark-on-strong`) : texte des boutons
  encre/rose/feuille, du badge Backoffice et des surfaces inversées. En sombre,
  il devient foncé.
- **Fond contrasté** (`contrast-bg`, `dark-contrast-bg`) et **texte contrasté**
  (`contrast-fg`, `dark-contrast-fg`) : en-tête et catégorie active ; ce couple
  conserve un fond sombre dans les deux thèmes.

Les bordures utilisent `color-mix(in srgb, var(--ink) 10%, transparent)`.
Les champs renforcent leur bordure à (25%) de la même encre.
Les fonds de succès, de badges et d’alerte sont des mélanges translucides des
rôles existants ; ils suivent automatiquement le thème. Aucun nouveau ramp de
couleur n’est défini en CSS. Les huit étapes OKLCH du sidecar sont des aperçus
synthétiques, pas des valeurs à reprendre dans l’implémentation.

**The Scoped Theme Rule.** Le choix Clair / Sombre / Système et l’attribut de
thème appartiennent au document du backoffice ; la vitrine conserve son identité
claire et son point d’entrée séparé. Sombre explicite et Système avec préférence
OS sombre utilisent exactement les mêmes substitutions de tokens.

**The Semantic Pair Rule.** Les actions fortes et le badge Backoffice associent
leur fond à `on-strong` ; l’en-tête et la catégorie active associent `contrast-bg`
à `contrast-fg` ; les champs associent `surface` à `ink`. Ces couples suivent la
variante active.

## Typography

**Display Font:** Fraunces, avec Georgia puis serif.
**Body Font:** DM Sans, avec ui-sans-serif, system-ui puis sans-serif.

Le contraste entre titres serif et commandes sans serif appartient à l’existant.
Les tailles réutilisées du backoffice sont consignées ci-dessus ; il n’existe pas
de ratio de progression déclaré.

### Hierarchy

- **Headline** : titre de page Fraunces fort ; rôle `headline`, puis
  `headline-wide` à partir de (48rem), avec la même hauteur de ligne.
- **Title** : titre de section Fraunces, rôle `title`. La variante de titre de
  carte explicitement dimensionnée emploie DM Sans, rôle `card-heading` ; les
  titres de carte simples gardent la taille héritée et la graisse (800).
- **Body** : texte de carte, rôle `body`. Les introductions de page et de panneau
  utilisent `lead`, avec `lead-large` pour la variante de page plus ample ; les
  introductions de section conservent une variante sans serif à (0.875rem/600).
- **Label** : libellé visible de champ, rôle `label` ; sa variante petite emploie
  (0.75rem/800). Les commandes utilisent `button`.

Le chargement des fontes fournit Fraunces 600/800 et DM Sans 400–800. Les
commandes et titres forts demandent désormais (800), disponible dans ces fontes.
Le titre de page d’erreur demande encore Fraunces (400), absent du chargement :
ce cas préexistant reste signalé, sans être promu dans la rampe réutilisable.
Les anciens surtitres et libellés en capitales espacées ne sont pas canonisés.

## Layout

Le contenu est centré dans un conteneur de (80rem) maximum ; la navigation
supérieure conserve (90rem). La page utilise (1.75rem) en haut, (3rem) en bas
et (1rem) de padding horizontal, puis (1.5rem) à partir de (40rem) et (2rem) à
partir de (64rem). Les pages ont un espacement de (1.5rem), avec une variante
large à (1.75rem) ; les piles internes réutilisent (0.5rem), (0.75rem) et (1rem).

La configuration passe d’un empilement à une grille de (14rem) plus une
colonne flexible à partir de (64rem). La barre latérale devient alors sticky
à (5.5rem). En dessous de (64rem), les catégories forment une navigation
horizontale défilante ; leurs intitulés restent visibles et les titres de groupe
sont masqués. L’en-tête est sticky au sommet, avec une hauteur minimale de
(4rem) ; les actions occupent une ligne complète jusqu’à (40rem), où le lien
Accueil public est masqué. Les autres breakpoints existants sont
(48rem), pour les titres et formulaires, et (80rem), pour les grilles larges.

Les tableaux de catalogue conservent leur structure sur mobile dans une région
à défilement horizontal, focalisable et nommée. Leur largeur minimale est
(36rem), contre (42rem) pour les tableaux génériques. Les messages réservent
(18rem) minimum ; les cellules utilisent (0.75rem) de padding. Les filtres
suivent une grille auto-fit de colonnes d’au moins (10rem), espacées de (1rem).
Les nouvelles lignes restent dans le tableau ; le thème n’altère pas ce modèle.

## Elevation & Depth

La profondeur vient principalement de surfaces tonales et de bordures fines.
Les panneaux et cartes de contenu standards n’ont aucune ombre ; l’en-tête
emploie un fond contrasté opaque, sans flou. Les ombres ambiantes conservées
appartiennent à des variantes précises, pas à tous les conteneurs.

### Shadow Vocabulary

- **Ombre légère** (`0 1px 2px rgb(0 0 0 / 0.05)`) : boutons contour hors
  en-tête, barres d’actions, cartes de catégories/modèles, statistiques d’import
  et avatar de marque. L’identité de serveur et les panneaux n’en ont plus.
- **Focus** (`0 0 0 2px color-mix(in srgb, var(--rose) 60%, transparent)`) :
  contrôles interactifs au clavier. Le tableau ajoute un contour encre de
  (3px), décalé de (3px).

Les transitions de couleur/fond/bordure et le filtre de survol durent (0.15s).
Les liens et boutons du backoffice sont généralement assombris par
`brightness(0.9)` au survol ; les boutons d’en-tête remplacent ce filtre par une
teinte contrastée à (10%). Ces transitions sont supprimées avec
`prefers-reduced-motion: reduce`. La bascule de thème révèle le nouvel état dans
un cercle centré sur le bouton pendant (450ms), avec
`cubic-bezier(0.16, 1, 0.3, 1)` ; elle applique immédiatement le thème si le
mouvement est réduit ou si View Transition est indisponible. Le document utilise
`scroll-behavior: auto`.

La médaille de rang de la fiche personnage et la carte d’erreur conservent leurs
ombres propres. Ce sont des exceptions locales, pas des tokens d’élévation à
reprendre pour de nouveaux panneaux.

## Shapes

Les grands conteneurs utilisent `panel` : panneaux, cartes, serveurs, cartes de
catégories/modèles et fiche personnage. Les petites surfaces utilisent `soft` :
identité, messages, validation, tables enveloppées et marques de catégories.
Les champs, liens de navigation et leurs icônes utilisent `control`.
Les boutons et badges restent en `pill` ; les avatars restent en `avatar`.
Les surfaces sont délimitées par une bordure de (1px).

Les arrondis plus larges de la page d’erreur et le petit arrondi du code inline
restent locaux. Certains anciens sous-conteneurs restent carrés, notamment les
icônes d’entrée, lignes, statistiques d’import, sous-formulaires et zone de
défilement d’emojis ; ce reliquat n’est pas une consigne pour les nouvelles surfaces.

## Components

### Buttons

Commandes compactes en pilule, DM Sans et texte fort. La hauteur minimale
standard est (2.5rem), avec les variantes (2.25rem), (2.75rem) et (3rem).
L’action encre devient rose au survol ; rose et feuille deviennent encre.
Le bouton contour conserve une surface neutre, puis une bordure et un texte rose.
Le bouton de suppression conserve son libellé et son traitement rose.
Dans l’en-tête, les boutons utilisent un fond transparent, le texte contrasté et
une bordure à (25%) de ce texte ; leur survol garde la même couleur de texte,
sans ombre.

Les variantes douces existantes emploient blush/encre ou blush/rose, avec
bordure mélangée depuis rose. La variante statique emploie line/muted.
Les boutons désactivés ont une opacité finale de (0.55) et le curseur approprié.

### Chips

Les badges en pilule portent un état ou un compteur. Les variantes sont rose
sur blush, feuille sur mélange feuille (15%), or sur mélange or (20%), et
muted sur line. Leur hauteur standard est (1.75rem), avec variante (2rem).
Les badges ne deviennent pas des filtres interactifs.

### Cards / Containers

Les cartes utilisent `surface`, une bordure sémantique et (1.25rem) de padding.
Les panneaux utilisent aussi `surface`, sans ombre, et des zones internes de
(1.25rem), avec variante entièrement rembourrée à (1.5rem). Les cartes de
catégories et de modèles utilisent `ivory`, une ombre légère, (1.5rem) de padding
et le rayon `panel` ; leur marque de (3.5rem) utilise `soft`. Les panneaux de
validation ajoutent une teinte feuille ou blush,
avec une explication écrite de l’état.

### Inputs / Fields

Champs à coins doux (`control`), largeur disponible, hauteur minimale (2.75rem),
padding horizontal (0.75rem), graisse de saisie (500). Les libellés restent visibles.
Le curseur est rose ; les placeholders utilisent `muted` avec opacité (1).
Les champs désactivés emploient `ivory` et `muted`. Les erreurs de nouvelles
lignes utilisent une bordure rose et un message associé.

### Theme Controls

Un bouton soleil/lune de (2.75rem) de large et de hauteur minimale (2.75rem)
alterne entre Clair et Sombre à partir du rendu courant ; son SVG de (1.2rem)
et son libellé accessible suivent cet état. Le bouton Système reste voisin,
avec `aria-pressed` et une teinte contrastée à (15%) quand il est sélectionné.
Ces commandes reprennent le traitement des boutons d’en-tête. Système est le
choix initial. La préférence `gachamelia.theme` est restaurée avant le CSS puis
synchronisée entre les onglets ; sans stockage, le choix fonctionne dans le
document courant. Les commandes sont désactivées pendant la révélation animée.

### Navigation

Liens de catégorie en DM Sans, icône à gauche, libellé et compteur à droite.
Les SVG utilisent un viewBox (24 × 24), un trait (1.75), des extrémités arrondies
et `currentColor`. Ils sont décoratifs pour l’accessibilité car le libellé reste
présent. L’état actif associe fond et texte contrastés, sans marqueur latéral ;
son icône hérite du texte sur fond transparent. Le survol utilise blush/encre.

**The Labeled Category Rule.** Une catégorie reste identifiée par un libellé
visible accompagné d’un SVG. Les emojis choisis pour les personnages restent
une donnée du catalogue, distincte de cette navigation.

### Catalogue Tables

En-têtes cliquables soulignés, `aria-sort`, nombres tabulaires, messages
préformatés avec retour à la ligne et action « Modifier » par ligne. Le
résultat des filtres et les confirmations sont annoncés dans une zone de statut.
Les nouvelles entrées ont le fond crème et une séparation encre de (2px).
La palette suit le thème sans changer ces signaux visuels.

## Do's and Don'ts

### Do:

- **Do** réutiliser les rôles CSS sémantiques et leurs couples de texte/fond.
- **Do** conserver Fraunces pour les titres et DM Sans pour les contrôles.
- **Do** réutiliser les rayons panel, soft et control selon la taille du conteneur.
- **Do** garder le libellé visible des catégories à côté de leur SVG.
- **Do** conserver les états, messages et focus dans les deux palettes.

### Don't:

- **Don't** appliquer la préférence de thème du backoffice à la vitrine.
- **Don't** remplacer un rôle de couleur par une valeur blanche/noire locale.
- **Don't** transformer les anciens surtitres espacés en règle pour de nouvelles surfaces.
- **Don't** traiter les aperçus de rampes du sidecar comme des tokens CSS existants.
