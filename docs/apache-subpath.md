# Déploiement Apache dans un sous-chemin

Le projet peut être servi sous un sous-chemin, par exemple `/gachamelia`, à condition
de configurer le même préfixe côté Symfony et côté build Encore.

## Variables à définir

```dotenv
APP_BASE_PATH=/gachamelia
DEFAULT_URI=https://example.test/gachamelia/
```

`APP_BASE_PATH` est utilisé pour générer les URLs d'assets et `DEFAULT_URI`
permet à Symfony de générer des URLs correctes hors requête HTTP.

Après avoir changé `APP_BASE_PATH`, il faut reconstruire les assets :

```shell
APP_BASE_PATH=/gachamelia npm run build
php bin/console cache:clear
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
