---
name: Gachamélia — backoffice
description: Identité crème, encre et rose avec une variante sombre réservée au backoffice.
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
  dark-cream: "#1d171b"
  ivory: "#fffaf4"
  dark-ivory: "#272126"
  surface: "#fff"
  dark-surface: "#30292e"
  ink: "#241813"
  dark-ink: "#f9eee8"
  muted: "#66544c"
  dark-muted: "#c4b5af"
  blush: "#f5ded6"
  dark-blush: "#422c34"
  on-strong: "#fff"
  dark-on-strong: "#1d171b"
typography:
  headline:
    fontFamily: "Fraunces, Georgia, serif"
    fontSize: "2.25rem"
    fontWeight: 400
    lineHeight: 1.25
  headline-wide:
    fontFamily: "Fraunces, Georgia, serif"
    fontSize: "3rem"
    fontWeight: 400
    lineHeight: 1.25
  title:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 900
  body:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    lineHeight: "1.5rem"
  label:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 700
  button:
    fontFamily: "DM Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 900
rounded:
  flat: "0"
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
  page: "2.5rem"
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
    rounded: "{rounded.flat}"
    padding: "0 0.75rem"
  navigation-active:
    backgroundColor: "{colors.blush}"
    textColor: "{colors.ink}"
    padding: "0 0.75rem"
  badge-rose:
    backgroundColor: "{colors.blush}"
    textColor: "{colors.rose}"
    rounded: "{rounded.pill}"
    padding: "0 0.75rem"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.flat}"
    padding: "1.25rem"
---

# Design System: Gachamélia — backoffice

## Overview

**Creative North Star: "Identité crème, encre et rose"**

Ce document décrit l’identité déjà présente et la variante sombre validée pour
le backoffice. Il ne propose pas de nouvelle identité. Le socle partagé reste
celui de `assets/styles/base.css` ; les composants et les substitutions du thème
sont définis dans `assets/styles/backoffice.css`.

Les titres Fraunces conservent leur caractère éditorial, tandis que DM Sans porte
les contrôles et les données. L’interface privilégie les panneaux rectangulaires,
les boutons en pilule et les repères rose. La variante sombre conserve ces formes,
ces espacements et les rôles de couleur ; elle modifie leurs valeurs uniquement.

**Key Characteristics:**
- Crème et ivoire en mode clair ; surfaces chaudes sombres dans le backoffice.
- Titres Fraunces, textes et contrôles DM Sans.
- Panneaux rectangulaires, boutons et badges en pilule.
- Catégories identifiées par un SVG accompagné d’un libellé.
- Couleurs sémantiques partagées par les composants des deux thèmes.

Périmètre : backoffice uniquement. `PRODUCT.md` reste la vérité produit. Les
captures de revue desktop/mobile clair/sombre ont été inspectées ; elles montrent
les mêmes composants et la même composition dans les deux palettes. Le mode
Système suit la préférence CSS : son affichage clair a été vérifié pendant la
revue ; le basculement de la préférence OS n’a pas été forcé. La configuration
`buildPath: comp` ne désigne aucune maquette approuvée ni contrat de fidélité.

## Colors

Les valeurs du frontmatter correspondent exactement aux déclarations CSS. Les
clés sans préfixe décrivent le mode clair ; les clés `dark-` décrivent les mêmes
rôles en mode sombre. Il existe douze rôles de couleur, chacun avec deux valeurs.
Les composants référencent le rôle CSS actif, jamais une palette copiée localement.

### Primary

- **Rose** (`rose`, `dark-rose`) : actions, liens, marqueur actif, erreurs,
  curseur de saisie et cases à cocher.
- **Rose doux** (`pink`, `dark-pink`) : accents secondaires de la même famille.

### Secondary

- **Feuille** (`leaf`, `dark-leaf`) : publication, succès et état valide.
- **Or** (`gold`, `dark-gold`) : compteurs et avertissements.
- **Encre d’or** (`gold-ink`, `dark-gold-ink`) : texte des badges dorés ; son
  rôle est distinct du fond doré translucide.

### Neutral

- **Crème** (`cream`, `dark-cream`) : fond de page et nouvelles lignes.
- **Ivoire** (`ivory`, `dark-ivory`) : panneaux, identité de serveur et navigation.
- **Surface** (`surface`, `dark-surface`) : champs, cartes et boutons contour.
- **Encre** (`ink`, `dark-ink`) : texte principal et actions fortes.
- **Texte discret** (`muted`, `dark-muted`) : indications et libellés secondaires.
- **Blush** (`blush`, `dark-blush`) : sélection, erreurs et accents doux.
- **Texte sur fond fort** (`on-strong`, `dark-on-strong`) : texte des boutons
  encre/rose/feuille et des surfaces inversées. En sombre, il devient foncé.

Les bordures utilisent `color-mix(in srgb, var(--ink) 10%, transparent)`.
Les fonds de succès, de badges et d’alerte sont des mélanges translucides des
rôles existants ; ils suivent automatiquement le thème. Aucun nouveau ramp de
couleur n’est défini en CSS. Les huit étapes OKLCH du sidecar sont des aperçus
synthétiques, pas des valeurs à reprendre dans l’implémentation.

**The Scoped Theme Rule.** Le choix Clair / Sombre / Système et l’attribut de
thème appartiennent au document du backoffice ; la vitrine conserve son identité
claire et son point d’entrée séparé.

**The Semantic Pair Rule.** Toute surface forte associe son rôle de fond à
`on-strong` ; les champs associent `surface` à `ink`. Ces couples suivent la
variante active.

## Typography

**Display Font:** Fraunces, avec Georgia puis serif.
**Body Font:** DM Sans, avec ui-sans-serif, system-ui puis sans-serif.

Le contraste entre titres serif et commandes sans serif appartient à l’existant.
Les tailles réutilisées du backoffice sont consignées ci-dessus ; il n’existe pas
de ratio de progression déclaré.

### Hierarchy

- **Headline** : titre de page ; rôle `headline`, puis `headline-wide` à partir
  du breakpoint de page large.
- **Title** : titre de section ; rôle `title`. Les titres de cartes utilisent
  aussi une variante observée à (1.25rem).
- **Body** : texte de carte ; rôle `body`. Les introductions utilisent
  (0.875rem), une graisse déclarée de (700) et une hauteur de ligne de (1.625).
- **Label** : libellé de champ, rôle `label` ; commandes, rôle `button`.

Les graisses consignées sont celles demandées par le CSS. Le chargement actuel
des fontes fournit Fraunces 600/800 et DM Sans 400–800 : les demandes Fraunces
400/900 et DM Sans 900 peuvent être substituées ou synthétisées. Cette divergence
préexistante est signalée, pas consacrée comme consigne de chargement future.
Les anciens surtitres en capitales espacées ne font pas partie de la rampe canonique.

## Layout

Le contenu et la navigation supérieure sont centrés dans un conteneur de
(90rem) maximum. La page utilise (2.5rem) de padding vertical et (1rem) de
padding horizontal, puis (1.5rem) à partir de (40rem) et (2rem) à partir de
(64rem). Les sections sont généralement séparées par (2rem).

La configuration passe d’un empilement à une grille de (16.5rem) plus une
colonne flexible à partir de (64rem). La barre latérale devient alors sticky
à (7rem). L’en-tête est sticky au sommet ; les actions se replient et occupent
une ligne complète jusqu’à (40rem). Les autres breakpoints existants sont
(48rem), pour les titres et formulaires, et (80rem), pour les grilles larges.

Les tableaux de catalogue conservent leur structure sur mobile dans une région
à défilement horizontal, focalisable et nommée. Leur largeur minimale est
(36rem), contre (42rem) pour les tableaux génériques. Les messages réservent
(18rem) minimum ; les cellules utilisent (0.75rem) de padding. Les filtres
suivent une grille auto-fit de colonnes d’au moins (10rem), espacées de (1rem).
Les nouvelles lignes restent dans le tableau ; le thème n’altère pas ce modèle.

## Elevation & Depth

La profondeur vient principalement de surfaces tonales et de bordures fines.
L’ombre réutilisée est discrète et ambiante ; elle ne crée aucun décalage dur.
L’en-tête utilise un fond ivoire à (92%) et un flou d’arrière-plan de (24px).

### Shadow Vocabulary

- **Ombre légère** (`0 1px 2px rgb(0 0 0 / 0.05)`) : panneaux, boutons contour,
  identité de serveur et avatar.
- **Focus** (`0 0 0 2px color-mix(in srgb, var(--rose) 60%, transparent)`) :
  contrôles interactifs au clavier. Le tableau ajoute un contour encre de
  (3px), décalé de (3px).

Les transitions de couleur/fond/bordure et le filtre de survol durent (0.15s).
Les liens et boutons du backoffice sont assombris par `brightness(0.9)` au
survol ; ces transitions sont supprimées avec `prefers-reduced-motion: reduce`.
Le document du backoffice utilise `scroll-behavior: auto`.

## Shapes

Les panneaux, cartes, tableaux et champs sont rectangulaires, sans arrondi
déclaré. Les boutons et badges utilisent le rôle `pill` ; les avatars utilisent
le rôle `avatar`. Les surfaces sont délimitées par une bordure de (1px).
Les arrondis plus larges de la page d’erreur constituent une variante spécifique,
pas une règle à appliquer aux tableaux.

## Components

### Buttons

Commandes compactes en pilule, DM Sans et texte fort. La hauteur minimale
standard est (2.5rem), avec les variantes (2.25rem), (2.75rem) et (3rem).
L’action encre devient rose au survol ; rose et feuille deviennent encre.
Le bouton contour conserve une surface neutre, puis une bordure et un texte rose.
Le bouton de suppression conserve son libellé et son traitement rose.

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
Les panneaux utilisent `ivory`, la légère ombre et des zones internes de
(1.5rem). Les panneaux de validation ajoutent une teinte feuille ou blush,
avec une explication écrite de l’état.

### Inputs / Fields

Champs carrés, largeur disponible, hauteur minimale (2.75rem), padding
horizontal (0.75rem), graisse de saisie (600). Les libellés restent visibles.
Le curseur est rose ; les placeholders utilisent `muted` avec opacité (1).
Les champs désactivés emploient `ivory` et `muted`. Les erreurs de nouvelles
lignes utilisent une bordure rose et un message associé.

Le sélecteur de thème emploie le même champ, avec hauteur minimale (2.5rem)
et largeur minimale (7rem). La préférence `gachamelia.theme` est restaurée
avant le chargement du CSS puis synchronisée entre les onglets ; en absence
de stockage, le choix reste utilisable dans le document courant.

### Navigation

Liens de catégorie en DM Sans, icône à gauche, libellé et compteur à droite.
Les SVG utilisent un viewBox (24 × 24), un trait (1.75), des extrémités arrondies
et `currentColor`. Ils sont décoratifs pour l’accessibilité car le libellé reste
présent. L’état actif associe blush/encre à un marqueur rose de (0.25rem × 1.75rem).

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
- **Do** garder le libellé visible des catégories à côté de leur SVG.
- **Do** conserver les états, messages et focus dans les deux palettes.

### Don't:

- **Don't** appliquer la préférence de thème du backoffice à la vitrine.
- **Don't** remplacer un rôle de couleur par une valeur blanche/noire locale.
- **Don't** transformer les anciens surtitres espacés en règle pour de nouvelles surfaces.
- **Don't** traiter les aperçus de rampes du sidecar comme des tokens CSS existants.
