# API bot Gachamélia

Cette documentation décrit le contrat HTTP que le bot Discord doit utiliser pour
remplacer les accès directs à la base de données. L'API est multi-serveur :
toutes les données de catalogue et de runtime sont scoped par `discordId` de
serveur Discord.

## Principes

- Toutes les routes métier sont sous `/api`.
- Les payloads et réponses sont en JSON.
- Les écritures du catalogue (`ranks`, `roles`, `stats`, `elements`, messages)
  sont faites par le backoffice. Le bot lit ce catalogue, puis écrit seulement
  l'état runtime des serveurs, emojis et membres.
- Les IDs numériques retournés par l'API sont les IDs internes backoffice. Les
  IDs Discord restent des chaînes.
- Si l'application est servie sous un sous-chemin, par exemple `/gachamelia`, le
  bot doit préfixer les routes : `/gachamelia/api/...`.

## Authentification

### Obtenir un token

```http
POST /api/auth/token
Authorization: Basic base64(client_id:client_secret)
```

Réponse `200` :

```json
{
  "token_type": "Bearer",
  "access_token": "eyJ...",
  "expires_in": 3600
}
```

Le bot doit mettre le token en cache jusqu'à son expiration, puis en redemander
un. Toutes les routes protégées attendent ensuite :

```http
Authorization: Bearer <access_token>
```

Erreurs :

- `401 {"error":"invalid_client"}` sur `/api/auth/token`
- `401 {"error":"unauthorized"}` sur les autres routes protégées

### Vérifier le token

Route optionnelle de debug :

```http
GET /api/me
Authorization: Bearer <access_token>
```

Réponse :

```json
{
  "client_id": "gachamelia-bot",
  "roles": ["ROLE_BOT"]
}
```

## Format des erreurs

Les erreurs métier renvoient généralement :

```json
{ "error": "server_not_found" }
```

Les erreurs Symfony génériques renvoient :

```json
{
  "error": "not_found",
  "message": "Not Found",
  "status": 404
}
```

Codes fréquents :

- `400 invalid_json` : JSON illisible.
- `400 invalid_payload` : champ obligatoire absent ou mauvais type.
- `401 unauthorized` : token Bearer absent/invalide.
- `404 server_not_found` : serveur inconnu du backoffice.
- `404 user_not_found` : fiche membre absente pour une route qui ne crée pas.
- `404 rank_not_found`, `role_not_found`, `element_not_found`, `stat_not_found`.
- `409 rank_catalogue_empty`, `role_catalogue_empty`, `element_catalogue_empty`.
- `409 staff_rank_not_found` : aucun rang `is_staff` dans le catalogue serveur.

## Séquence recommandée côté bot

Au démarrage :

1. Récupérer un token via `POST /api/auth/token`.
2. Pour chaque serveur où le bot est présent, appeler `POST /api/discord-servers`.
3. Rafraîchir le cache des emojis du bot via `PUT /api/discord-emojis`.
4. Rafraîchir le cache des emojis de chaque serveur via `PUT /api/discord-emojis`.
5. Charger le catalogue serveur via `GET /api/discord-servers/{discordId}/catalogue`.

À l'arrivée d'un membre, au ready/load des membres, sur `/ficheperso`, ou avant
un message de départ :

1. Appeler `PUT /api/discord-servers/{discordId}/users/{userDiscordId}`.
2. Utiliser la réponse `user` pour lire le rang, le rôle, les éléments et les stats.
3. Utiliser le catalogue en cache pour retrouver les messages welcome/bye du rang.

Pour garder exactement le tirage actuel côté Java, le bot peut tirer lui-même
`rank_id`, `role_id` et `element_ids` depuis le catalogue, puis les envoyer dans
le `PUT`. Si ces champs ne sont pas envoyés, l'API complète les valeurs
manquantes avec des choix par défaut pondérés.

## Serveurs Discord

### Créer ou rafraîchir le serveur minimal

```http
POST /api/discord-servers
Authorization: Bearer <access_token>
Content-Type: application/json
```

Payload :

```json
{
  "discord_id": "123456789012345678",
  "name": "Dev-Bots",
  "icon": "discord-icon-hash"
}
```

`icon` peut être `null`. Il correspond au hash Discord, pas à une URL complète.

Réponses :

- `201` si le serveur vient d'être créé.
- `200` si le serveur existait et que son cache a été rafraîchi.

```json
{
  "server": {
    "discord_id": "123456789012345678",
    "name": "Dev-Bots",
    "icon": "discord-icon-hash"
  }
}
```

## Cache emojis

### Rafraîchir les emojis du bot

```http
PUT /api/discord-emojis
Authorization: Bearer <access_token>
Content-Type: application/json
```

Payload :

```json
{
  "source": "bot",
  "emojis": [
    {
      "id": "111111111111111111",
      "name": "gachamelia",
      "animated": true,
      "available": true
    }
  ]
}
```

### Rafraîchir les emojis d'un serveur

```json
{
  "source": "server",
  "discord_server_id": "123456789012345678",
  "emojis": [
    {
      "id": "222222222222222222",
      "name": "ambre",
      "animated": false,
      "available": true
    }
  ]
}
```

Réponse :

```json
{
  "cache": {
    "source": "server",
    "cache_key": "server:123456789012345678",
    "received": 1,
    "available": 1
  }
}
```

Les emojis déjà en cache mais absents du nouveau snapshot sont marqués
`available=false`, ils ne sont pas supprimés.

Erreurs spécifiques :

- `400 invalid_source` si `source` n'est pas `bot` ou `server`.
- `404 server_not_found` pour un cache serveur inconnu.

## Catalogue serveur

### Lire le catalogue complet

```http
GET /api/discord-servers/{discordId}/catalogue
Authorization: Bearer <access_token>
```

Réponse :

```json
{
  "server": {
    "discord_id": "123456789012345678",
    "name": "Dev-Bots",
    "icon": "discord-icon-hash"
  },
  "catalogue": {
    "ranks": [
      {
        "id": 1,
        "discord_id": "987654321098765432",
        "name": "Novice",
        "percentage": 35,
        "bye_title": "Novice sortant",
        "is_staff": false,
        "stats": [
          { "id": 10, "name": "Force", "percentage": 70 }
        ],
        "welcome_messages": [
          { "id": 100, "message": "Bienvenue %username%." }
        ],
        "bye_messages": [
          { "id": 200, "message": "A bientot %username%." }
        ]
      }
    ],
    "roles": [
      {
        "id": 2,
        "name": "Comete",
        "percentage": 45,
        "emoji": {
          "source": "server",
          "unicode": null,
          "id": "123456789012345678",
          "name": "comete",
          "animated": false,
          "markup": "<:comete:123456789012345678>",
          "cdn_url": "https://cdn.discordapp.com/emojis/123456789012345678.webp?size=64&quality=lossless"
        }
      }
    ],
    "stats": [
      { "id": 10, "name": "Force" }
    ],
    "elements": [
      {
        "id": 3,
        "name": "Ambre",
        "emoji": {
          "source": "unicode",
          "unicode": "🌘",
          "id": null,
          "name": null,
          "animated": false,
          "markup": "🌘",
          "cdn_url": null
        }
      }
    ]
  }
}
```

Utilisation actuelle côté bot :

- `ranks` remplace `RankRepository.findAll()`.
- `roles` remplace `RoleRepository.findAll()`.
- `elements` remplace `ElementRepository.findAll()`.
- `stats` remplace `StatRepository.findAll()`.
- `rank.welcome_messages` remplace `getRandomWelcomeMessage(rank)`.
- `rank.bye_messages` remplace `getRandomByeMessage(rank)`.

Le bot doit gérer le cas d'un catalogue incomplet : sans rang, rôle ou élément,
les routes runtime ne peuvent pas initialiser une fiche complète.

## Fiches membres runtime

### Créer ou assurer une fiche membre

```http
PUT /api/discord-servers/{discordId}/users/{userDiscordId}
Authorization: Bearer <access_token>
Content-Type: application/json
```

Payload minimal :

```json
{}
```

Payload avec attribution explicite :

```json
{
  "rank_id": 1,
  "role_id": 2,
  "element_ids": [3],
  "initialize_stats": true
}
```

Payload pour forcer le rang staff :

```json
{
  "staff": true
}
```

Réponses :

- `201` si la fiche vient d'être créée.
- `200` si la fiche existait déjà.

```json
{
  "user": {
    "id": 50,
    "discord_id": "424242424242424242",
    "rank": {
      "id": 1,
      "discord_id": "987654321098765432",
      "name": "Novice",
      "is_staff": false
    },
    "role": {
      "id": 2,
      "name": "Comete"
    },
    "elements": [
      { "id": 3, "name": "Ambre" }
    ],
    "stats": [
      { "id": 10, "name": "Aura", "value": 0 },
      { "id": 11, "name": "Force", "value": 0 }
    ]
  }
}
```

Comportement :

- Crée la fiche si elle n'existe pas.
- Applique d'abord `staff`, `rank_id`, `role_id`, `element_ids` si présents.
- Si le rang manque encore, choisit un rang non-staff depuis le catalogue.
- Si le rôle manque encore, choisit un rôle depuis le catalogue.
- Si les éléments sont vides, ajoute un élément du catalogue.
- Initialise les stats manquantes à `0`, sauf si `initialize_stats=false`.

`staff=true` prend le dessus sur `rank_id` si les deux sont envoyés.

### Modifier les attributions d'une fiche existante

```http
PATCH /api/discord-servers/{discordId}/users/{userDiscordId}
Authorization: Bearer <access_token>
Content-Type: application/json
```

Payload :

```json
{
  "rank_id": 1,
  "role_id": 2,
  "element_ids": [3, 4]
}
```

Ou pour appliquer le rang staff :

```json
{
  "staff": true
}
```

Cette route ne crée pas la fiche et n'initialise pas les stats. Elle met à jour
uniquement les champs présents. `element_ids` remplace toute la liste des
éléments du membre.

Réponse : même format `user` que la route `PUT`.

### Ecrire les stats d'une fiche

```http
PUT /api/discord-servers/{discordId}/users/{userDiscordId}/stats
Authorization: Bearer <access_token>
Content-Type: application/json
```

Payload :

```json
{
  "stats": [
    { "id": 10, "value": 12 },
    { "id": 11, "value": 7 }
  ]
}
```

Comportement :

- La fiche doit déjà exister.
- Chaque entrée est créée ou mise à jour dans `user_stats`.
- `value` est un entier.
- Les `id` doivent correspondre à des stats du même serveur.

Réponse : même format `user` que la route `PUT`.

## Mapping depuis le bot Java actuel

| Code actuel | Remplacement API |
| --- | --- |
| `UserService.getOrCreateUser(discordId)` | `PUT /api/discord-servers/{guildId}/users/{discordId}` |
| `UserRepository.update(user)` pour rang/rôle/éléments | `PATCH /api/discord-servers/{guildId}/users/{discordId}` |
| `StatRepository.saveUserStat(userStat)` | `PUT /api/discord-servers/{guildId}/users/{discordId}/stats` |
| `StatRepository.getUserStats(user)` | champ `user.stats` retourné par `PUT` ou `PUT /stats` |
| `RankRepository.findAll()` | `GET /api/discord-servers/{guildId}/catalogue`, champ `catalogue.ranks` |
| `RoleRepository.findAll()` | `catalogue.roles` |
| `ElementRepository.findAll()` | `catalogue.elements` |
| `StatRepository.findAll()` | `catalogue.stats` |
| `getRandomWelcomeMessage(rank)` | tirer dans `rank.welcome_messages` du catalogue en cache |
| `getRandomByeMessage(rank)` | tirer dans `rank.bye_messages` du catalogue en cache |
| `getRankStaff()` | trouver le rang `is_staff=true` dans `catalogue.ranks` ou utiliser `{"staff": true}` |

## Flux par événement Discord

### Ready

1. Token.
2. Upsert serveur.
3. Refresh emojis bot et serveur.
4. Lire catalogue.
5. Pour chaque membre chargé : `PUT /users/{discordId}`.
6. Si le membre a le rôle Discord staff : `PUT /users/{discordId}` avec
   `{"staff": true}`.
7. Ajouter côté Discord le rôle lié au `user.rank.discord_id` retourné.

### Arrivée membre

1. `PUT /users/{discordId}`.
2. Ajouter le rôle Discord `user.rank.discord_id`.
3. Retrouver le rang complet dans le catalogue par `user.rank.id`.
4. Tirer un message dans `rank.welcome_messages`.
5. Construire l'embed avec `user.rank`, `user.role`, `user.elements`.

### Départ membre

1. `PUT /users/{discordId}` pour récupérer la fiche existante ou la créer si
   elle n'existait pas.
2. Retrouver le rang complet dans le catalogue par `user.rank.id`.
3. Tirer un message dans `rank.bye_messages`.
4. Utiliser `rank.bye_title` comme titre si non-null, sinon le fallback bot.

### Ajout du rôle staff

1. `PUT /users/{discordId}` avec `{"staff": true}`.
2. Ajouter côté Discord le rôle `user.rank.discord_id` retourné.

### Commande `/ficheperso`

1. `PUT /users/{discordId}`.
2. Utiliser `user.rank`, `user.role`, `user.elements` et `user.stats`.
3. Retrouver les détails emojis du rôle/élément via le catalogue si nécessaire.

## Variables côté bot recommandées

```dotenv
API_BASE_URL=https://example.test/gachamelia
API_CLIENT_ID=gachamelia-bot
API_CLIENT_SECRET=change-me
```

`API_BASE_URL` ne doit pas finir obligatoirement par `/`; le client Java peut
normaliser en supprimant le slash final avant d'ajouter `/api/...`.
