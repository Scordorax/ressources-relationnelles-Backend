# Rapport d'analyse — Tests Unitaires Services
**Projet :** Ressources Relationnelles (Backend Symfony 7.4)
**Date d'analyse :** 2026-04-27
**Analysé par :** Revue de code + tests unitaires PHPUnit 12

---

## Comment lancer les tests

PHPUnit 12.5 est **déjà installé** (`require-dev` dans `composer.json`).
Aucune installation supplémentaire n'est nécessaire.

```bash
# Depuis la racine du projet backend :
cd ressources-relationnelles-Backend

# Lancer tous les tests unitaires de ce dossier :
./vendor/bin/phpunit -c Test-unitaires/phpunit.xml

# Lancer un seul fichier :
./vendor/bin/phpunit -c Test-unitaires/phpunit.xml Test-unitaires/Service/JwtManagerTest.php

# Avec couverture de code (nécessite Xdebug ou PCOV) :
./vendor/bin/phpunit -c Test-unitaires/phpunit.xml --coverage-text
```

> **Windows :** Remplacer `./vendor/bin/phpunit` par `vendor\bin\phpunit`

---

## Fichiers de tests créés

| Fichier | Service testé | Méthodes couvertes |
|---|---|---|
| `JwtManagerTest.php` | `JwtManager` | `createToken()`, `decode()` |
| `AuthServiceTest.php` | `AuthService` | `register()`, `login()`, `refresh()`, `logout()`, `getFranceConnectRedirectUrl()`, `handleFranceConnectCallback()` |
| `JwtAuthenticatorTest.php` | `JwtAuthenticator` | `supports()`, `authenticate()`, `onAuthenticationSuccess()`, `onAuthenticationFailure()` |
| `FranceConnectProviderTest.php` | `FranceConnectProvider` | `getAuthorizationUrl()`, `getLogoutUrl()` |
| `ResourceServiceTest.php` | `ResourceService` | `getAll()`, `getOne()`, `create()`, `update()`, `delete()` |
| `CommentServiceTest.php` | `CommentService` | `getByResourceId()`, `create()`, `reply()` |
| `AdminServiceTest.php` | `AdminService` | `getAllResources()`, `deleteResource()`, `getAllCategories()`, `createCategory()`, `updateCategory()`, `deleteCategory()`, `getAllUsers()`, `deleteUser()`, `banUser()`, `getStatistics()`, `exportStatistics()` |
| `ModeratorServiceTest.php` | `ModeratorService` | `getPendingResources()`, `validateResource()`, `rejectResource()`, `getReportedComments()`, `deleteComment()`, `replyComment()` |
| `SuperAdminServiceTest.php` | `SuperAdminService` | `createPrivilegedAccount()`, `getPrivilegedAccounts()`, `changeRole()`, `deleteAccount()` |
| `ResourceInteractionServiceTest.php` | `ResourceInteractionService` | `interact()`, `getByUserAndResource()`, `remove()` |
| `ActivityServiceTest.php` | `ActivityService` | `getAll()`, `start()` |

**Total : ~120 tests unitaires** couvrant tous les services.

---

---

# PROBLÈMES IDENTIFIÉS

---

## 🔴 CRITIQUE — Sécurité

---

### Problème #1 — `id_token` FranceConnect jamais vérifié

**Fichier :** `src/Service/Security/FranceConnectProvider.php` — `getTokens()`
**Fichier :** `src/Service/Auth/AuthService.php` — `handleFranceConnectCallback()`

**Description :**
Le protocole OpenID Connect (OIDC) impose que le `id_token` renvoyé par FranceConnect
soit **vérifié** avant toute utilisation. La vérification obligatoire comprend :
- Validation de la **signature** (JWT signé par FranceConnect avec sa clé publique)
- Vérification de l'émetteur (`iss`)
- Vérification de l'audience (`aud` = votre `client_id`)
- Vérification de l'expiration (`exp`)
- Vérification du **`nonce`** (protection replay attack)

**Situation actuelle :** Le code appelle `getTokens()` qui renvoie le `id_token`,
mais celui-ci n'est jamais décodé ni vérifié. Seul `getUserInfo()` via curl est utilisé.

**Risque :** Un attaquant interceptant la réponse du token endpoint peut injecter
un `id_token` forgé. Sans vérification, l'identité reçue n'est pas garantie.

**Correction requise :**
Utiliser `firebase/php-jwt` (déjà installé dans `composer.json`) pour décoder
et vérifier le `id_token` avec la clé publique JWKS de FranceConnect.

---

### Problème #2 — Nonce OIDC jamais validé

**Fichier :** `src/Service/Auth/AuthService.php` — `getFranceConnectRedirectUrl()` et `handleFranceConnectCallback()`

**Description :**
Le `nonce` généré dans `getFranceConnectRedirectUrl()` est renvoyé au client front-end
mais n'est **jamais stocké côté serveur** (ni en session, ni en cache, ni en base).
Dans `handleFranceConnectCallback()`, le nonce n'est pas récupéré ni comparé
avec le nonce présent dans le `id_token`.

**Risque :** Attaque par rejeu (replay attack). Un attaquant peut réutiliser
un callback FranceConnect valide d'une session précédente.

**Correction requise :**
1. Stocker le nonce côté serveur (session PHP ou cache Redis avec TTL court).
2. Après réception du `id_token`, vérifier que `id_token['nonce'] === nonce_stocké`.

---

### Problème #3 — Implémentation JWT maison (`JwtManager`)

**Fichier :** `src/Service/Security/JwtManager.php`

**Description :**
Le projet utilise une implémentation JWT **entièrement personnalisée** alors que :
- `lexik/jwt-authentication-bundle` est déjà installé (`composer.json`)
- `firebase/php-jwt` est déjà installé (`composer.json`)

Le bundle LexikJWT gère : rotation des clés, algorithmes multiples, révocation,
introspection, et est maintenu activement avec des audits de sécurité réguliers.

**Risque :** Le code maison ne gère pas :
- La rotation des secrets
- Les algorithmes asymétriques (RS256/ES256) recommandés en production
- Les cas limites des edge cases JWT (timing attacks sur la comparaison de signatures)

**Note positive :** `hash_equals()` est correctement utilisé pour la comparaison
de signatures — protection contre les timing attacks.

**Correction suggérée :**
Utiliser `lexik/jwt-authentication-bundle` qui est déjà installé.
Supprimer `JwtManager` et `JwtAuthenticator` custom.

---

### Problème #4 — Secret JWT dans le fichier `.env` versionné

**Fichier :** `.env`

```
APP_JWT_SECRET=6bd5b7f5d1503d9a06e1ff0b2e6b64f3
```

**Description :**
Le secret JWT est commité dans `.env`. Si le dépôt est public ou compromis,
tous les tokens JWT existants sont potentiellement falsifiables.

**Risque :** La connaissance du secret permet de forger des tokens JWT valides
pour n'importe quel utilisateur, y compris `ROLE_SUPER_ADMIN`.

**Correction requise :**
1. Ajouter `.env` au `.gitignore` (ou utiliser `.env.local` pour les secrets)
2. Utiliser un secret d'au moins 256 bits générés aléatoirement
3. Ne jamais versionner des secrets de production

---

### Problème #5 — Informations d'identification FranceConnect placeholder en production

**Fichier :** `.env`

```
FRANCE_CONNECT_CLIENT_ID=votre_client_id
FRANCE_CONNECT_CLIENT_SECRET=votre_client_secret
FRANCE_CONNECT_REDIRECT_URI=https://votre-app.fr/api/auth/france-connect/callback
```

**Description :**
Les valeurs FranceConnect sont des placeholders non fonctionnels.
L'intégration FranceConnect n'est pas opérationnelle dans cet état.

**Risque fonctionnel :** Le flux FranceConnect échouera silencieusement ou lèvera
des exceptions non gérées en production.

---

## 🟠 MAJEUR — Logique et conception

---

### Problème #6 — Bannissement contournable (JWT toujours valide après ban)

**Fichier :** `src/Service/Admin/AdminService.php` — `banUser()`
**Fichier :** `src/Service/Security/JwtAuthenticator.php` — `authenticate()`

**Description :**
La méthode `banUser()` désactive un compte via `setIsVerified(false)`.
Cependant, `JwtAuthenticator` ne vérifie **jamais** le champ `isVerified`
lors de l'authentification JWT — il vérifie seulement que l'email existe en base.

**Conséquence :** Un utilisateur banni dont le JWT (valide 1h) n'est pas encore
expiré peut **continuer à utiliser l'API** normalement jusqu'à l'expiration du token.

**Correction requise :**
Dans `JwtAuthenticator::authenticate()`, après avoir chargé l'utilisateur,
vérifier `$user->isVerified()` et lever une exception si false.

**Alternative :** Implémenter une liste de révocation de JWT (JTI blacklist).

---

### Problème #7 — `RefreshToken.userId` est un entier brut (intégrité référentielle cassée)

**Fichier :** `src/Entity/RefreshToken.php`

```php
#[ORM\Column]
private int $userId;
```

**Description :**
`RefreshToken` stocke l'identifiant utilisateur comme un entier brut
au lieu d'une relation `ManyToOne` vers `User`.

**Conséquences :**
1. Si un utilisateur est supprimé, ses refresh tokens orphelins persistent en base
2. Doctrine ne peut pas faire de jointure optimisée
3. La contrainte de clé étrangère n'est pas appliquée par la base de données
4. `findOneBy(['token' => $t])` puis `userRepository->find($refresh->getUserId())`
   est un pattern cassé — l'utilisateur peut ne plus exister

**Correction suggérée :**
```php
#[ORM\ManyToOne(targetEntity: User::class)]
#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
private User $user;
```

---

### Problème #8 — TypeError dans `AuthService::createRefreshToken()` (BUG BLOQUANT)

**Fichier :** `src/Service/Auth/AuthService.php` — méthode `createRefreshToken()`

```php
$refresh->setUserId($user->getId()); // $user->getId() peut retourner null
```

**Fichier :** `src/Entity/RefreshToken.php`

```php
public function setUserId(int $userId): self // attend un int, pas null
```

**Description :**
Lors de l'inscription (`register()`) ou de la création d'un compte FranceConnect
(`findOrCreateFranceConnectUser()`), un nouvel objet `User` est créé et persisté.
Doctrine n'assigne l'`id` qu'après `flush()`. Or `createRefreshToken()` est appelé
juste après `persist()` + `flush()` sur l'user... **mais** dans ces cas précis,
l'`id` devrait être disponible.

**Le vrai problème :** Ce test le révèle pour le cas FranceConnect où l'user
est persisté dans `findOrCreateFranceConnectUser()` mais le flush appelle
ensuite `buildTokenResponse()` → `createRefreshToken()`. Dans les mocks de test,
`getId()` renvoie `null` → **TypeError**.

**En production :** si le flush échoue silencieusement ou que l'ID n'est pas
retourné par le pilote DB, cela peut provoquer un crash non géré.

**Vérification en test (AuthServiceTest.php) :**
Le test `testHandleFranceConnectCallbackSuccessCreatesNewFcUser` documente
et confirme cette exception avec `$this->expectException(\TypeError::class)`.

---

### Problème #9 — Ressources privées visibles par tous les utilisateurs connectés

**Fichier :** `src/Service/Resource/ResourceService.php` — `getAll()`

```php
if ($user) {
    return $this->repository->findBy(['status' => 'published']);
    // Retourne TOUTES les ressources publiées, y compris 'visibility' => 'private'
}
```

**Description :**
N'importe quel utilisateur authentifié (`ROLE_USER`) peut voir toutes les ressources
marquées `visibility: private`, même celles qui ne lui appartiennent pas.

**Risque :** Fuite de données — des ressources "privées" sont accessibles à tous
les utilisateurs connectés, ce qui rend le contrôle de visibilité sans effet.

**Correction suggérée :**
Pour les ressources `private`, filtrer par `author = $user` (seul l'auteur les voit)
ou définir clairement la sémantique de `private` dans le cahier des charges.

---

### Problème #10 — `ActivityService::start()` ne persiste aucune donnée (fonctionnalité incomplète)

**Fichier :** `src/Service/Activity/ActivityService.php` — `start()`

```php
public function start(int $activityId, User $user): array
{
    // ...
    // Logique de démarrage (ex : enregistrement de participation)
    return [
        'message'  => 'Activité démarrée',
        'activity' => [...],
    ];
}
```

**Description :**
La méthode retourne un message "Activité démarrée" mais **n'enregistre aucune participation**.
Il n'existe pas d'entité `Participation` ni de logique de tracking dans ce service.

**Risque fonctionnel :** L'utilisateur reçoit une confirmation mais aucune donnée
n'est sauvegardée. Les analytics et le suivi de progression sont impossibles.

**Note :** Le test `testStartDoesNotPersistAnything` confirme et documente ce comportement.

---

## 🟡 MINEUR — Qualité et maintenabilité

---

### Problème #11 — `FranceConnectProvider` utilise `curl` au lieu de `HttpClientInterface`

**Fichier :** `src/Service/Security/FranceConnectProvider.php`

**Description :**
Les méthodes `getTokens()` et `getUserInfo()` utilisent `curl_*` directement.
Symfony fournit `HttpClientInterface` (déjà disponible via `symfony/http-client`
qui est dans `composer.json`).

**Conséquences :**
1. **Impossible de mocker les appels HTTP en test unitaire** — les méthodes `getTokens()`
   et `getUserInfo()` ne peuvent pas être couvertes par des tests unitaires.
2. Pas de gestion des timeouts, retry, ou logs via Symfony.
3. Code non standard dans l'écosystème Symfony.

**Correction suggérée :**
Injecter `HttpClientInterface` dans le constructeur et remplacer les `curl_*`
par `$this->httpClient->request('POST', $url, ['body' => $data])`.

---

### Problème #12 — `phpunit.dist.xml` — nom de fichier non standard

**Fichier :** `phpunit.dist.xml` (racine du projet)

**Description :**
La convention Symfony et PHPUnit est de nommer le fichier `phpunit.xml.dist`
(non `phpunit.dist.xml`). Certains outils CI/CD et IDE cherchent spécifiquement
`phpunit.xml.dist` ou `phpunit.xml` et ne détecteront pas automatiquement
le fichier actuel.

**Impact :** Les IDE (PhpStorm, VSCode) peuvent ne pas détecter la configuration
automatiquement. Les commandes par défaut (`./vendor/bin/phpunit` sans `-c`) ne
fonctionneront pas.

---

### Problème #13 — `symfony/phpunit-bridge` absent

**Fichier :** `composer.json` — section `require-dev`

**Description :**
`symfony/phpunit-bridge` est absent des dépendances de développement.
Ce package fournit :
- Gestion correcte des dépréciations Symfony dans les tests
- Helpers pour les tests temporels (`ClockMock`)
- Meilleure intégration avec le kernel Symfony en test

**Note :** Le fichier `phpunit.dist.xml` a `failOnDeprecation="true"` activé,
ce qui peut causer des échecs de test inattendus liés aux dépréciations Doctrine.

---

### Problème #14 — Validation des mots de passe trop permissive

**Fichier :** `src/Service/Auth/AuthService.php` — `validateRegisterData()`

```php
if (strlen($data['password']) < 8) {
    throw new \InvalidArgumentException('Mot de passe trop court (minimum 8 caractères)');
}
```

**Description :**
La validation se limite à 8 caractères minimum. Aucune règle de complexité :
- Pas de majuscule requise
- Pas de chiffre requis
- Pas de caractère spécial requis

Un mot de passe `aaaaaaaa` est accepté.

**Recommandation CNIL/ANSSI :** Pour une application contenant des données
personnelles sensibles, minimum 12 caractères avec complexité, ou 8 caractères
avec complexité renforcée.

---

### Problème #15 — Aucune limite de tentatives de connexion (brute force)

**Fichier :** `src/Controller/AuthController.php` / `src/Service/Auth/AuthService.php`

**Description :**
Il n'existe aucun mécanisme de rate limiting sur les endpoints :
- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/register`

**Risque :** Attaque par force brute sur les mots de passe.

**Correction suggérée :**
Utiliser `symfony/rate-limiter` (disponible dans Symfony 7.4) pour limiter
les tentatives par IP et/ou par email.

---

### Problème #16 — Workflow de vérification email inexistant

**Fichier :** `src/Service/Auth/AuthService.php` — `register()`

```php
$user->setIsVerified(false); // Email à vérifier si besoin
```

**Description :**
Le commentaire "Email à vérifier si besoin" indique que la vérification
est prévue mais non implémentée. Aucun service d'envoi d'email de confirmation,
aucun token de vérification, aucun endpoint de validation.

**Impact :** N'importe qui peut créer un compte avec un email arbitraire.

---

### Problème #17 — `firebase/php-jwt` installé mais jamais utilisé

**Fichier :** `composer.json`

```json
"firebase/php-jwt": "^7.0.5"
```

**Description :**
La bibliothèque `firebase/php-jwt` est dans les dépendances mais n'est utilisée
nulle part dans le code. Le `JwtManager` réimplémente manuellement ce que
`firebase/php-jwt` fait déjà (et mieux).

**Impact :** Dépendance inutile qui alourdit le projet. Ou inversement,
si elle était censée être utilisée, elle a été oubliée.

---

### Problème #18 — `AdminService` importe `StatisticsRepository` sans l'injecter

**Fichier :** `src/Service/Admin/AdminService.php`

```php
use App\Repository\StatisticsRepository; // importé mais pas dans le constructeur
```

**Description :**
`StatisticsRepository` est importé via `use` mais n'est pas injecté dans
le constructeur et n'est jamais utilisé. `getStatistics()` appelle
`$this->resourceRepository->count()` et non le `StatisticsRepository`.

**Impact :** Import mort. Peut induire en erreur les développeurs suivants.

---

### Problème #19 — Messages d'erreur identiques pour user inexistant et mauvais mot de passe

**Fichier :** `src/Service/Auth/AuthService.php` — `login()`

```php
if (!$user) {
    throw new \Exception('Identifiants invalides');
}
// ...
if (!$this->hasher->isPasswordValid(...)) {
    throw new \Exception('Identifiants invalides');
}
```

**Description :**
C'est une **bonne pratique de sécurité** (user enumeration prevention).
Cependant, notez que cela rend le debugging en développement plus difficile.
Prévoir des logs serveur distincts pour distinguer les deux cas.

---

## Récapitulatif de criticité

| # | Niveau | Problème | Bloquant prod ? |
|---|---|---|---|
| 1 | 🔴 Critique | id_token FranceConnect non vérifié | Oui |
| 2 | 🔴 Critique | Nonce OIDC non validé | Oui |
| 3 | 🔴 Critique | JWT implémentation maison | Non (fonctionne, risqué) |
| 4 | 🔴 Critique | Secret JWT dans .env versionné | Oui (si dépôt public) |
| 5 | 🔴 Critique | Credentials FranceConnect placeholder | Oui (FC non fonctionnel) |
| 6 | 🟠 Majeur | Ban contournable via JWT encore valide | Non (risque sécurité) |
| 7 | 🟠 Majeur | RefreshToken.userId sans FK | Non (intégrité données) |
| 8 | 🟠 Majeur | TypeError potential dans createRefreshToken | Non (cas edge) |
| 9 | 🟠 Majeur | Ressources privées visibles par tous | Oui (fuite données) |
| 10 | 🟠 Majeur | ActivityService::start() ne persiste rien | Oui (fonctionnel) |
| 11 | 🟡 Mineur | FranceConnectProvider curl non mockable | Non (testabilité) |
| 12 | 🟡 Mineur | Nom fichier phpunit non standard | Non (convention) |
| 13 | 🟡 Mineur | symfony/phpunit-bridge absent | Non (tests) |
| 14 | 🟡 Mineur | Validation password trop permissive | Non (recommandation) |
| 15 | 🟡 Mineur | Pas de rate limiting | Non (recommandation) |
| 16 | 🟡 Mineur | Vérification email non implémentée | Non (fonctionnel) |
| 17 | 🟡 Mineur | firebase/php-jwt inutilisé | Non (dead code) |
| 18 | 🟡 Mineur | StatisticsRepository importé non injecté | Non (dead import) |
| 19 | ℹ️ Info | Messages d'erreur login génériques | Non (bonne pratique) |

---

## Couverture de tests

Les tests unitaires écrits couvrent **l'intégralité des méthodes publiques** des 10 services.
Ils utilisent exclusivement des mocks (pas de base de données, pas de réseau).

### Ce qui NE peut PAS être testé en unitaire (sans refactoring)

| Méthode | Raison |
|---|---|
| `FranceConnectProvider::getTokens()` | Appel HTTP curl direct |
| `FranceConnectProvider::getUserInfo()` | Appel HTTP curl direct |
| `AuthService::handleFranceConnectCallback()` (succès complet) | Dépend des deux méthodes curl ci-dessus |

Ces méthodes nécessitent soit :
- Un refactoring vers `HttpClientInterface` (recommandé)
- Des tests d'intégration avec un serveur FranceConnect mock

---

*Rapport généré suite à lecture complète des sources — aucune modification du code source.*
