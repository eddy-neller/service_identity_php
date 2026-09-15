# Verrous (Symfony Lock)

Les verrous vivent dans le **Redis du service, base 1**. Jamais dans `flock`, sauf en test.

```yaml
# config/packages/lock.yaml
framework:
    lock: '%env(LOCK_DSN)%'     # .env.dist : LOCK_DSN="${REDIS_URL}/1"

when@test:
    framework:
        lock: 'flock'
```

Un seul verrou a aujourd'hui un effet réel : celui de `UserNotifier`, qui écarte les e-mails à token
envoyés en double.

**Le verrou reste efficace quel que soit le nombre de workers — à condition d'être dans Redis.**
C'est le store qui décide de sa portée, pas le composant : un verrou Redis est vu par tous les
conteneurs, un verrou `flock` seulement par les processus du conteneur qui l'a posé.

Le piège est que **`flock` suffit tant qu'il n'y a qu'un seul conteneur `worker`**, parce que ses
deux consommateurs partagent le même `/tmp`. Tout fonctionne — jusqu'au jour où le worker passe à deux
répliques : avec `flock`, les doublons reviennent alors sans une seule erreur. Avec Redis, rien ne
change.

## Qui prend un verrou

Relevé le 2026-09-14 dans le conteneur compilé (`lock.default.factory` est injecté dans six services) :

| Consommateur | Actif ? | Rôle |
|---|---|---|
| `UserNotifier` | **oui** | écarte un e-mail d'activation ou de réinitialisation déjà en cours d'envoi |
| `messenger.middleware.deduplicate_middleware` | non | ne verrouille que les messages portant un `DeduplicateStamp` — aucun n'en porte |
| `limiter.register_activation_ip`, `limiter.reset_password_ip`, `limiter.register_activation_email`, `limiter.reset_password_email` | non | déclarés dans `rate_limiter.yaml`, **injectés nulle part** |

## `UserNotifier` : une protection parmi trois

> Le mécanisme complet — composition de la clé, rôle du token, journalisation — est décrit dans
> [`domain_events.md`](domain_events.md), section « Déduplication à l'émission des e-mails à token ».
> Ce document ne reprend que ce qui concerne le **store**.

Un e-mail à token traverse trois protections distinctes. Elles ne se remplacent pas : chacune couvre
un cas que les autres laissent passer.

| Protection | Écarte | Où | Partagée par |
|---|---|---|---|
| Ledger `processed_domain_event` | le **même événement** retraité après un succès (retry, redélivrance) | `SendActivationEmailHandler`, `SendResetPasswordEmailHandler` | Postgres |
| Verrou de `UserNotifier` | deux envois **simultanés** du **même token** | `UserNotifier` | **Redis** — la seule que `flock` casse |
| `MAX_TOKEN_REQUESTS = 3` | les demandes au-delà de trois pour un même compte | modèle `User` | Postgres |

### Le cas que seul le verrou couvre

Les handlers ne transportent pas le token : ils **relisent le token courant** sur l'agrégat. Deux
demandes rapprochées produisent deux événements distincts — deux `event_id`, donc le ledger les laisse
passer tous les deux — qui peuvent lire le **même** token, le dernier émis, et l'envoyer au même
instant. L'utilisateur recevrait deux e-mails identiques.

Le verrou porte sur `user.<canal>.<userId>.<sha256 tronqué du token>` : le second envoi ne l'obtient
pas, il est journalisé (`Duplicate user mail discarded…`) et abandonné.

```php
$lock = $this->lockFactory->createLock($deduplicationKey, self::DEDUPLICATION_TTL);  // 300 s

if (!$lock->acquire()) {          // non bloquant
    return;                       // après journalisation
}

try {
    $this->mailer->sendEmail(...);
} finally {
    $lock->release();
}
```

- Le verrou est relâché **après chaque tentative, y compris en erreur**, pour que le retry de
  l'événement puisse reprendre. Il protège donc des rafales, pas d'une cadence.
- Le **TTL de 300 s** n'est qu'un filet, si le worker meurt en tenant le verrou.
- Un token régénéré produit une clé différente : une nouvelle demande légitime part toujours.

> Le docblock de `DEDUPLICATION_TTL` dit que « le middleware le relâche ». C'est en réalité le
> `finally` de `UserNotifier` : ce verrou est posé à la main, pas par `DeduplicateMiddleware`.

### Ce qui reste non couvert

Un crash **entre l'envoi et `markProcessed()`** : l'e-mail est parti, le ledger ne le sait pas, le
retry le renvoie. Aucun des trois mécanismes ne peut l'empêcher — il faudrait une clé d'idempotence
supportée par le fournisseur e-mail. Limite assumée, documentée dans `domain_events.md` (§ 5).

## Pourquoi Redis

Un verrou n'a de sens que s'il est vu par **tous** les processus susceptibles d'envoyer le même
e-mail. `flock` pose un verrou sur un fichier du `/tmp` **du conteneur**.

Mesuré le 2026-09-14, même nom de verrou, deux processus lancés à une seconde d'écart :

| `LOCK_DSN` | Processus 1 | Processus 2 | |
|---|---|---|---|
| `flock` | `worker-1` : obtenu | `worker-1` (même conteneur) : **refusé** | fonctionne — même `/tmp` |
| `flock` | `app-1` : obtenu | `worker-1` : **obtenu aussi** | ne protège plus rien |
| `redis://redis:6379/1` | `app-1` : obtenu | `worker-1` : **refusé** | un verrou pour tout le service |

**La première ligne est ce qui rend le piège dangereux** : c'est la configuration d'aujourd'hui, et
elle se comporte correctement. La deuxième est celle de demain, dès que le worker est répliqué —
c'est l'objet même de la séparation `app` / `worker`, et ce que fera Kubernetes.

### Pourquoi la base 1

Le cache applicatif occupe la base 0 (`REDIS_URL` sans index) : `cache.app`, `cache.tag`,
`cache.rate_limiter`, `cache.jwt_auth`. Les verrous sont en base 1 :

- purger le cache (`FLUSHDB` sur la base 0) n'emporte pas un verrou en cours ;
- `redis-cli -n 1 KEYS '*'` ne montre que des verrous.

Redis tourne en `appendonly yes` : un verrou tenu pendant un redémarrage de Redis survit, et expire
alors par son TTL.

Ce Redis est **dédié** à ce service. Ne pas le mutualiser avec celui de `service_shop`.

## Les limiteurs de `rate_limiter.yaml`

Symfony leur injecte la `LockFactory` pour rendre `consume()` atomique : lire l'état dans
`cache.rate_limiter`, l'incrémenter, le réécrire. Mais **aucun code ne les utilise**. Ce sont eux qui
porteraient un vrai plafond par fenêtre (3 e-mails / 30 min) ; s'ils sont branchés un jour, l'état
(`cache.rate_limiter`, déjà sur Redis) **et** le verrou doivent être partagés, sans quoi le quota est
multiplié par le nombre de répliques.

Les limites effectivement appliquées aujourd'hui sont ailleurs :

- dans le modèle `User` : `MAX_TOKEN_REQUESTS = 3`, `ActivationLimitReachedException` /
  `ResetPasswordLimitReachedException`, converties en 429 par `api_platform.yaml` — état persisté en
  Postgres, donc partagé par construction ;
- dans la passerelle Kong, par IP, sur `/api/users/register` et `/api/users/reset-password`
  (`back_php/gateway/AGENTS.md`).

## Pourquoi `flock` en test

- La CI (`.gitlab-ci.yml`) ne démarre **que Postgres** : aucun Redis n'y est joignable.
- Les tests tournent dans un seul processus, transports Messenger en `in-memory://` (`.env.test`) :
  `flock` suffit.
- `UserNotifierTest` mocke `LockFactory` : il vérifie le comportement du notifier, pas le store.

**Conséquence : aucun test ne prouve l'exclusion entre conteneurs.** Elle se vérifie à la main, stack
démarrée — même script que `service_shop/docs/lock.md` :

```bash
cat > /tmp/lock_proof.php <<'PHP'
<?php
require '/var/www/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\StoreFactory;

(new Dotenv())->bootEnv('/var/www/.env');
usleep((int) (getenv('DELAY_MS') ?: 0) * 1000);

$lock = (new LockFactory(StoreFactory::createStore($_SERVER['LOCK_DSN'])))->createLock('proof-isolation', 30);
$ok = $lock->acquire(false);
printf("%s LOCK_DSN=%s obtenu=%s\n", gethostname(), $_SERVER['LOCK_DSN'], $ok ? 'OUI' : 'non');
if ($ok) { sleep(4); $lock->release(); }
PHP

docker exec -i en_shop_php_service_identity-app-1 php < /tmp/lock_proof.php &
docker exec -i -e DELAY_MS=1000 en_shop_php_service_identity-worker-1 php < /tmp/lock_proof.php
wait
# attendu : un OUI, un non. Tester entre DEUX conteneurs : dans un seul, flock passerait aussi.
```

## Règles

- Jamais `flock` (ni aucun store local) hors de `when@test`.
- Redis du service uniquement, base 1 — jamais celui de `service_shop`.
- Tout nouveau verrou porte un **TTL**, et une clé préfixée par son contexte (`user.…`).
- Une vérification d'exclusion se fait **entre deux conteneurs**, jamais entre deux processus d'un
  même conteneur.
