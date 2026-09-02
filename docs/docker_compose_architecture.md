# Architecture Docker Compose

`docker-compose.yaml` décrit le workload portable de `service_identity`. Il ne contient que les
processus et données nécessaires au service ; le poste de développement est décrit séparément dans
`docker-compose.override.yaml`, chargé automatiquement par Docker Compose en local.

## Topologie

```text
gateway Kong
    |
service-identity (edge)
    |
nginx ──> app (PHP-FPM)
            |
            +── database (PostgreSQL)
            +── rabbitmq
            +── redis

worker ─────┘
```

`nginx` est le seul service rejoint au réseau externe `en_shop_php_edge`, dans l'override local. Il
porte l'alias `service-identity`, consommé par Kong. Les services de données restent sur le réseau
Compose `default` : ni la passerelle ni les autres bounded contexts ne peuvent les joindre.

`identity-nginx` est un alias privé sur `default`, réservé à un éventuel proxy interne. Il ne doit
pas être confondu avec l'alias public `service-identity` du réseau `edge`.

## Services

| Service | Rôle | Démarrage |
|---|---|---|
| `database` | PostgreSQL du bounded context Identity | singleton avec volume `database_data` |
| `rabbitmq` | transports Messenger | singleton avec volume `rabbitmq_data` |
| `redis` | cache et données Redis dédiées | singleton avec volume `redis_data` |
| `app` | PHP-FPM, trafic HTTP | attend les trois dépendances saines |
| `worker` | cron et consommateurs Messenger | même image que `app`, attend les mêmes dépendances |
| `nginx` | proxy FastCGI vers les replicas `app` | attend `app` |

Les healthchecks de PostgreSQL, RabbitMQ et Redis forment une vraie barrière pour `app` et `worker`.
Leur démarrage ne dépend donc pas d'une disponibilité présumée des infrastructures.

## Une image, deux rôles

`app` et `worker` réemploient la même ancre de build et la même image
`en_shop_php_service_identity_app:latest`. La variable `SUPERVISOR_ROLE` sélectionne les processus :

- `web` démarre PHP-FPM ;
- `worker` démarre cron et les consommateurs `async` et `domain_events`.

Cette séparation permet de mettre le web et les consommateurs à l’échelle indépendamment, tout en
garantissant qu’ils exécutent le même artefact.

## Base et override

Le fichier de base ne publie aucun port, ne monte pas le code de l'hôte et utilise la cible Docker
`prod`. L'override local ajoute les ports publiés, les bind mounts, la cible `dev`, Mailpit et le
réseau `edge`. Une image de production peut donc être déployée avec le seul fichier de base, sans
emporter Xdebug ou une dépendance au système de fichiers du développeur.

Varnish n'appartient plus à cette stack : le cache HTTP du catalogue est porté par `service_shop`.
