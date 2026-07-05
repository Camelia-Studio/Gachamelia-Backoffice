# Déploiement Apache dans un sous-chemin

Le projet peut être servi sous un sous-chemin, par exemple `/gachamelia`, à condition
de configurer le même préfixe côté Symfony et côté build Encore.

## Variables à définir

```dotenv
APP_BASE_PATH=/gachamelia
DEFAULT_URI=https://example.test/gachamelia/
```

`APP_BASE_PATH` est utilisé par le front controller pour que Symfony reconnaisse
le sous-chemin public pendant les requêtes HTTP, et par Encore pour préfixer les
assets compilés. Le build Encore lit les fichiers `.env` Symfony, dont
`.env.local`, avant de calculer son `publicPath`. Les assets publics classiques,
comme `public/images`, utilisent ensuite le base path de la requête Symfony ; il
ne faut donc pas aussi le définir dans `framework.assets.base_path`, sinon les
URLs sont préfixées deux fois.
`DEFAULT_URI` permet à Symfony de générer des URLs correctes hors requête HTTP.

Après avoir changé `APP_BASE_PATH`, il faut reconstruire les assets :

```shell
npm run build
php bin/console cache:clear
```

Une variable d'environnement réellement exportée dans le shell garde la priorité
sur les fichiers `.env`, ce qui permet toujours de forcer ponctuellement un
autre préfixe :

```shell
APP_BASE_PATH=/autre-chemin npm run build
```

## Apache recommandé : alias vers `public/`

```apache
Alias /gachamelia /var/www/gachamelia/public

<Directory /var/www/gachamelia/public>
    AllowOverride All
    Require all granted
</Directory>
```

Dans ce mode, `public/.htaccess` gère le fallback vers `index.php` et conserve
le sous-chemin dans les URLs.

## Apache sans modification du vhost

Si le vhost pointe vers un dossier parent commun, par exemple
`/Volumes/Melaine/Coding/came/demo`, le projet peut être placé dans un
sous-dossier sans ajouter d'alias Apache :

```text
/Volumes/Melaine/Coding/came/demo/
└── gachamelia/
    ├── .htaccess
    └── public/
```

Dans ce mode, le `.htaccess` à la racine délègue les requêtes vers `public/`.
Le front controller normalise ensuite la requête avec `APP_BASE_PATH` pour que
`/gachamelia/` soit traité comme la page d'accueil de l'application, et non
comme une route `/gachamelia/`.

## Apache compatible : alias vers la racine du projet

```apache
Alias /gachamelia /var/www/gachamelia

<Directory /var/www/gachamelia>
    AllowOverride All
    Require all granted
</Directory>
```

Dans ce mode, le `.htaccess` à la racine envoie les assets existants vers
`public/` et les autres requêtes vers `public/index.php`.

## Pré-requis Apache

- Apache 2.4 ou plus récent.
- `mod_rewrite` activé.
- `AllowOverride All` ou règles équivalentes dans la configuration Apache.
- Le handler PHP/FPM configuré par le vhost ou la configuration globale Apache.
