# Développement et vérifications

Le frontend utilise AssetMapper et des modules JavaScript/CSS natifs. Aucun build
Node n'est requis. Les deux points d'entrée sont `app` (vitrine) et `backoffice`.

Le site local est accessible à `http://localhost:39000/gachamelia-backoffice/`.
Les modifications d'interface sont vérifiées avec Playwright sur cette instance.
Pour les essais authentifiés, demander au propriétaire de terminer la connexion
Discord dans le navigateur ; ne pas remplacer ce parcours par une authentification
de développement. Le serveur `Dev-Bots` et son catalogue vide sont prévus pour les
essais de l'issue #8. Les tests automatisés restent séparés dans la base `_test`.

## Environnement Docker local

Dans l'installation `web-infra`, le projet est monté dans le conteneur
`web-infra-httpd-1` sous `/app/gachamelia-backoffice`.

Symfony ne charge pas `.env.local` en environnement `test`. Définir la connexion
MySQL locale dans `.env.test.local` (ignoré par Git), avec le même nom de base que
dans `.env.local`. Doctrine ajoute automatiquement le suffixe `_test` ; ne pas
l'ajouter dans `DATABASE_URL`.

Depuis le conteneur, après avoir configuré les identifiants :

```shell
cd /app/gachamelia-backoffice
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php vendor/bin/phpunit --no-coverage
```

Les tests réinitialisent les données de la base de test. Les identifiants locaux
restent hors du dépôt et du ZIP de release.

## Assets et livraison

En développement, AssetMapper sert les fichiers de `assets/`. Pour valider la
livraison en production :

```shell
APP_ENV=prod APP_DEBUG=0 php bin/console asset-map:compile
bin/build-release-archive verification
```

Le ZIP contient `assets/`, `importmap.php` et `public/assets/`. Consulter
`apache-subpath.md` pour le déploiement sous un préfixe d'URL. Supprimer uniquement
le dossier généré `public/assets/` après cette vérification pour retrouver le mode
développement et voir les changements des sources sans recompiler.
