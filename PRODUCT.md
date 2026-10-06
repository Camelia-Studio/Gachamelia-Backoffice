# Gachamélia

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Public confirmé par le propriétaire : les administrateurs de serveurs Discord qui configurent Gachamélia. La vitrine s’adresse aux futurs utilisateurs pour leur présenter le bot.

Le code prévoit aussi des gestionnaires de modèles de catalogue, avec des permissions globales distinctes. Il s’agit d’un rôle observé dans l’implémentation, pas d’un public principal supplémentaire confirmé.

## Product Purpose

Présenter Gachamélia et permettre aux administrateurs de configurer le bot pour leur serveur Discord.

Le contenu existant décrit un bot gacha communautaire qui transforme les arrivées Discord en invocations avec rareté, rôle, élément et fiche personnage. Cette description est issue du dépôt ; aucun positionnement comparatif ni résultat chiffré n’a été confirmé.

## Operating Context

- La vitrine publique est accessible à `/` ; le backoffice commence à `/app`.
- L’utilisateur se connecte avec Discord, puis retrouve ses serveurs et leurs possibilités de configuration.
- Les droits de configuration dépendent des permissions Discord ; la gestion des modèles de catalogue dépend de rôles globaux spécifiques.
- L’application existante utilise Symfony 7.4, PHP >= 8.3, Twig et Doctrine.
- Les textes et les documents présents sont principalement en français.

## Capabilities and Constraints

Fonctionnalités observées dans le code, à préserver lors de futurs travaux d’interface sauf changement explicite de périmètre :

- Authentification Discord et sélection des serveurs.
- Configuration des canaux, du rôle staff, des rangs, rôles de personnage, statistiques, affinités élémentaires et probabilités.
- Gestion des messages d’arrivée et de départ.
- Catalogues, modèles réutilisables, import de modèles et import CSV avec prévisualisation et validation.
- Consultation des fiches personnage.
- Distinction entre serveurs actifs et inactifs, avec configuration en lecture seule lorsque le serveur est inactif.
- Respect des permissions et isolation des données entre serveurs ; l’interface doit refléter les restrictions réellement appliquées par le serveur.

Une documentation décrit le déploiement Apache sous un sous-chemin (`docs/apache-subpath.md`). L’hébergeur et l’URL de production ne sont pas confirmés.

Le front repose sur AssetMapper (sans build Node) avec du CSS natif : `assets/styles/base.css` (jetons et reset), `app.css` (vitrine, préfixe `lp-`) et `backoffice.css` (backoffice, préfixe `bo-`). En développement, `symfony serve` suffit ; en production, `php bin/console asset-map:compile` est nécessaire.

## Brand Commitments

Noms présents dans le dépôt : Gachamélia et Camélia Studio. Assets existants : `public/images/gachamelia-bot-avatar.png` et `public/images/gachamelia-hero.jpg`.

Recommandation : conserver ces noms et employer un français clair, accessible aux administrateurs qui ne sont pas développeurs. Leur traitement visuel n’est pas fixé par cette initialisation.

## Evidence on Hand

- `templates/home/index.html.twig` : présentation du bot et démonstration de son mécanisme.
- `templates/backoffice/` et `src/Controller/` : parcours et fonctionnalités existants.
- `docs/api-bot.md` : contrat documenté de l’API du bot.
- `docs/apache-subpath.md` : contraintes documentées de déploiement sous un préfixe.
- `public/images/` : illustration et avatar existants.

La démonstration de la page d’accueil utilise des valeurs aléatoires ; elle ne constitue pas une métrique d’utilisation réelle. Aucun témoignage, chiffre d’adoption ou bénéfice mesuré n’a été validé pendant cette initialisation.

## Product Principles

Recommandations proposées à la demande du propriétaire :

1. Permettre à un administrateur de comprendre puis configurer son serveur sans connaissances techniques.
2. Préserver les parcours fonctionnels et les permissions lors des changements d’interface.
3. Rendre visibles la portée des modifications, les erreurs de validation et les restrictions d’accès.
4. Présenter le fonctionnement réel du bot sans inventer de preuves, de statistiques ou de promesses.

## Accessibility & Inclusion

Recommandation, et non certification de l’existant : viser WCAG 2.2 AA, avec navigation au clavier, focus visible, libellés explicites, erreurs compréhensibles, respect de la réduction des animations et utilisation sur mobile comme sur ordinateur.

## Open Decisions

- Positionnement distinctif au-delà du mécanisme décrit dans le dépôt.
- Hébergement et URL de production.
- Besoin éventuel de langues supplémentaires ; recommandation initiale : français principal.
- Besoins d’accessibilité spécifiques au public et validation formelle de la cible recommandée.
