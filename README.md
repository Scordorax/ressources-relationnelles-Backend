# Ressources Relationnelles — Backend

API REST construite avec **Symfony 7.4** et **API Platform 4**, sécurisée par **JWT**. Elle expose les ressources nécessaires à l'application de partage de ressources relationnelles.

---

## Stack technique

| Composant | Technologie |
|---|---|
| Langage | PHP 8.2+ |
| Framework | Symfony 7.4 |
| API | API Platform 4.3 |
| Base de données | MySQL 8.0 |
| ORM | Doctrine ORM 3 |
| Authentification | JWT (LexikJWTAuthenticationBundle) + Refresh Token |
| Mailer (dev) | Mailpit |
| Messenger | Doctrine transport |
| CORS | NelmioCorsBundle |

---

## Prérequis

Avant de commencer, assure-toi d'avoir installé :

- **PHP 8.2+** avec les extensions `ctype`, `iconv`, `pdo_mysql`, `openssl`, `intl`
- **Composer** — [getcomposer.org](https://getcomposer.org)
- **Symfony CLI** (recommandé) — [symfony.com/download](https://symfony.com/download)
- **MySQL 8.0+** (ou MariaDB 10.11+)
- **Docker + Docker Compose** (optionnel, pour lancer la BDD et le mailer en local)
- **OpenSSL** (pour générer les clés JWT)

---

## Lancer le projet avec Docker (recommandé)

C'est la méthode la plus simple — aucune installation de PHP, Composer ou MySQL sur ta machine.

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)

C'est tout.

### Démarrage

```bash
git clone <url-du-repo>
cd ressources-relationnelles-Backend

# Construire et démarrer tous les services
docker compose up -d --build
```

Puis initialiser la base de données et les clés JWT :

```bash
docker compose exec app bash docker/init.sh
```

L'API est disponible sur **http://localhost:8080**
La doc Swagger sur **http://localhost:8080/api**
L'interface mail (Mailpit) sur **http://localhost:8025**

### Commandes Docker utiles

```bash
# Voir les logs
docker compose logs -f

# Lancer une commande Symfony dans le conteneur
docker compose exec app php bin/console <commande>

# Accéder au shell du conteneur
docker compose exec app bash

# Arrêter les services
docker compose down

# Tout supprimer (conteneurs + volumes BDD)
docker compose down -v
```

---

## Installation manuelle (sans Docker)

### 1. Cloner le dépôt

```bash
git clone <url-du-repo>
cd ressources-relationnelles-Backend
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer les variables d'environnement

Copie le fichier `.env` en `.env.local` et adapte les valeurs :

```bash
cp .env .env.local
```

Modifie au minimum ces variables dans `.env.local` :

```dotenv
# Connexion à la base de données MySQL
DATABASE_URL="mysql://root:TON_MOT_DE_PASSE@127.0.0.1:3306/ressource_relationnelles?serverVersion=8.0.32&charset=utf8mb4"

# Secret applicatif Symfony (génère une valeur aléatoire)
APP_SECRET=une_chaine_aleatoire_longue

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=ta_passphrase_jwt
```

### 4. Générer les clés JWT

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

> La passphrase saisie doit correspondre à `JWT_PASSPHRASE` dans ton `.env.local`.

Ou via la commande Symfony (si le bundle est bien configuré) :

```bash
php bin/console lexik:jwt:generate-keypair
```

### 5. Créer la base de données et jouer les migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

---

## Lancer le projet

### Avec le serveur Symfony CLI (recommandé)

```bash
symfony server:start
```

L'API sera disponible sur `https://localhost:8000`.

### Avec le serveur PHP intégré

```bash
php -S localhost:8000 -t public/
```

---

## Lancer les services Docker (BDD + Mailer)

Si tu ne veux pas installer MySQL localement, Docker Compose fournit une base PostgreSQL et un serveur mail Mailpit :

> ⚠️ Le `compose.yaml` utilise **PostgreSQL**, mais le `.env` est configuré pour **MySQL**. Adapte l'un ou l'autre selon ton choix.

```bash
docker compose up -d
```

Services démarrés :
- **PostgreSQL 16** — port exposé dynamiquement (voir `docker compose ps`)
- **Mailpit** — interface web sur `http://localhost:8025`, SMTP sur le port `1025`

---

## Structure du projet

```
src/
├── Controller/         # Contrôleurs REST (Auth, User, Resource, Comment, Admin…)
├── Entity/             # Entités Doctrine (User, Resource, Comment, Activity…)
├── Repository/         # Repositories Doctrine
├── Service/            # Logique métier organisée par domaine
│   ├── Auth/
│   ├── Resource/
│   ├── Comment/
│   ├── Admin/
│   └── …
config/
├── packages/           # Configuration des bundles (security, jwt, cors…)
migrations/             # Migrations Doctrine
```

### Entités principales

| Entité | Description |
|---|---|
| `User` | Utilisateur avec rôles (ROLE_USER, ROLE_ADMIN…) |
| `Resource` | Ressource partagée (titre, contenu, type, statut, visibilité) |
| `Category` | Catégorie d'une ressource |
| `Comment` | Commentaire sur une ressource (avec réponses imbriquées) |
| `Activity` | Activité créée par un utilisateur |
| `ResourceInteraction` | Interaction d'un user sur une ressource (like, favori…) |
| `Statistics` | Statistiques de vues/recherches par ressource |
| `RefreshToken` | Token de rafraîchissement JWT |

---

## Authentification

L'API utilise **JWT** via `LexikJWTAuthenticationBundle`.

- `POST /api/login` — obtenir un access token
- `POST /api/token/refresh` — rafraîchir le token
- Les requêtes protégées nécessitent le header : `Authorization: Bearer <token>`

---

## Documentation API

API Platform génère automatiquement une documentation interactive :

- **Swagger UI** : `http://localhost:8000/api`
- **JSON-LD / Hydra** : `http://localhost:8000/api/docs.jsonld`

---

## Commandes utiles

```bash
# Lister toutes les routes
php bin/console debug:router

# Créer une migration après modification d'une entité
php bin/console make:migration

# Jouer les migrations
php bin/console doctrine:migrations:migrate

# Vider le cache
php bin/console cache:clear

# Lancer les tests
php bin/phpunit
```

---

## Variables d'environnement clés

| Variable | Description |
|---|---|
| `APP_ENV` | Environnement (`dev`, `prod`, `test`) |
| `APP_SECRET` | Clé secrète Symfony |
| `DATABASE_URL` | DSN de connexion à la base de données |
| `JWT_SECRET_KEY` | Chemin vers la clé privée JWT |
| `JWT_PUBLIC_KEY` | Chemin vers la clé publique JWT |
| `JWT_PASSPHRASE` | Passphrase de la clé privée JWT |
| `CORS_ALLOW_ORIGIN` | Regex des origines autorisées pour le CORS |
| `MAILER_DSN` | DSN du mailer (`null://null` en dev pour ignorer) |
| `MESSENGER_TRANSPORT_DSN` | Transport Symfony Messenger |
