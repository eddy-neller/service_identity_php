# Événements d'intégration : ce que ce service annonce aux autres

> Côté **émission** uniquement. Les Domain Events de ce service, son outbox et son worker :
> [`domain_events.md`](domain_events.md). Ce que le consommateur en fait, et sa déduplication :
> [`../../service_shop/docs/integration_events.md`](../../service_shop/docs/integration_events.md).
>
> Sources : [`src/Infrastructure/Messaging/`](../src/Infrastructure/Messaging/) — et les décisions
> de conception de ces trois classes, avec ce qui casse si on les modifie mal :
> [`src/Infrastructure/Messaging/Messaging.md`](src/Infrastructure/Messaging/Messaging.md).

## Ce que ce service publie, et ce qu'il ignore

Ce service annonce des **faits publics** sur un topic SNS. Il ne demande rien, n'attend pas de
réponse, et **ne sait pas qui l'écoute** : un topic, pas une file. `service_shop` est le premier
abonné ; il n'est pas nommé dans ce dépôt.

Trois propriétés en découlent, et ce sont elles qui comptent plus que le code :

- **Aucun appel HTTP est-ouest.** Ce service ne connaît ni l'API, ni la base, ni le modèle de ses
  consommateurs. Il n'a aucun moyen de savoir si un client a été créé quelque part.
- **Aucune classe partagée.** Les événements du `Domain` ne sont pas le contrat : ils sont
  **traduits** en `UserIntegrationEvent` à la frontière. Renommer `UserRegisteredEvent` ou changer
  ses propriétés ne casse donc aucun consommateur.
- **Aucune donnée personnelle sur le bus.** Le contrat ne transporte que `userAccountId` — ni
  e-mail, ni nom. Les consommateurs n'en ont pas besoin, et un bus est un endroit durable.

## Le contrat, version 1

Topic `identity-user-events`, corps JSON, abonnements en `RawMessageDelivery=true` — le consommateur
reçoit donc le corps tel quel, pas l'enveloppe de notification de SNS.

```json
{ "id": "<eventId>", "type": "identity.user.registered", "version": 1,
  "occurredAt": "2026-09-15T12:00:00+00:00", "data": { "userAccountId": "<uuid>" } }
```

| Type | Événement du Domain | Ce que le shop en fait |
|---|---|---|
| `identity.user.registered` | `UserRegisteredEvent` | provisionne le client |
| `identity.user.created_by_admin` | `UserCreatedByAdminEvent` | idem |
| `identity.user.deleted` | `UserDeletedByAdminEvent` | désactive, ou pose une pierre tombale |

Ces trois-là couvrent tous les cas : il n'existe aucun événement de suppression par l'utilisateur
lui-même. Le provisionnement part de l'**inscription**, pas de l'activation — un compte jamais activé
a donc un client chez le consommateur. C'est le comportement d'origine, conservé tel quel.

**`id` est l'`eventId()` du Domain Event** qui a déclenché la publication, et il ne doit jamais être
régénéré : c'est la clé de déduplication des consommateurs. Un rejeu depuis `failed_domain_events`
doit publier le même `id`, sans quoi le consommateur traitera le fait deux fois.

## Comment le faire évoluer sans coordonner un déploiement

C'est la raison d'être du versionnage, et la règle est courte :

| Changement | `VERSION` | Pourquoi |
|---|---|---|
| **Ajouter** un champ | inchangée | les lecteurs ignorent les clés qu'ils ne connaissent pas |
| **Ajouter** un type | inchangée | un consommateur qui ne le connaît pas journalise et acquitte |
| Renommer ou retirer un champ, changer un type, changer le sens à clé constante | **incrémenter** | ça casse un lecteur existant |

**Incrémenter pour un ajout serait une rupture gratuite** : le consommateur acquitte sans agir tout
ce qui dépasse la version qu'il connaît, donc le provisionnement s'arrêterait en silence.

Et quand il faut vraiment incrémenter : **les consommateurs se livrent d'abord**. Eux savent
acquitter une version qu'ils ne connaissent pas ; l'inverse n'est pas vrai.

Les trois tolérances sur lesquelles cette liberté repose sont testées **chez le consommateur**, et
listées dans sa doc. Ce ne sont pas des suppositions : elles bornent ce que ce service peut changer
seul.

> **Ce que rien ne vérifie.** Le consommateur garde les fixtures JSON du contrat dans son
> `UserIntegrationEventSerializerTest`, et ce service le décrit de son côté dans
> `UserIntegrationEventTest`. **Aucun test ne compare les deux rives** : les dépôts sont séparés, et
> changer `toContract()` en mettant à jour son propre test laisse les deux suites vertes. Seule la
> vérification de bout en bout attrape la rupture. Un vrai test *consumer-driven* — le consommateur
> publie ses attentes, la CI du producteur les vérifie — reste à écrire, et le deviendra nécessaire
> au deuxième consommateur.

## Le chemin d'une publication

```text
cas d'usage ──▶ agrégat enregistre son Domain Event
     │
     ▼  publié dans la transaction, via l'outbox PostgreSQL (doctrine://)
transport `domain_events`
     │
     ▼  worker : messenger:consume domain_events
handler sur `event.bus`   hasProcessed ? ──▶ publier ──▶ markProcessed
     │
     ▼
SnsUserIntegrationEventPublisher ──▶ sns:Publish sur `identity-user-events`
```

**La publication n'a pas de worker à elle.** Elle a lieu dans le consommateur d'outbox déjà en
place : un fait métier est d'abord durable en base, puis annoncé. Jamais l'inverse — publier avant
le commit annoncerait un fait qu'un rollback pourrait effacer.

### Un effet externe, donc hors transaction

Publier est un appel réseau : il ne peut pas rejoindre la transaction du handler, et ne doit surtout
pas être placé dans un `transactional()` — ce serait tenir une transaction PostgreSQL ouverte
pendant un aller-retour HTTP.

La garantie est donc l'**au-moins-une-fois dédupliqué** : `hasProcessed()` en garde d'entrée,
publication, puis `markProcessed()` après succès. C'est le second usage documenté par
`DomainEventLedgerInterface`, celui de `SendActivationEmailHandler`. Une panne entre la publication
et le marquage fait republier le même `id` — et c'est précisément pour ça que les consommateurs
dédupliquent.

**L'ordre est le bon et ne doit pas être inversé** : publier puis marquer risque un doublon, que le
consommateur absorbe ; marquer puis publier risque une **perte définitive**, que personne ne rattrape.

### Le piège qui coûterait un événement perdu

**`publish()` d'AsyncAws est asynchrone.** Sans `resolve()`, la requête partirait au mieux à la
destruction de l'objet et une erreur HTTP ou réseau ne remonterait jamais : le handler marquerait
l'événement comme traité, et le fait serait perdu **sans la moindre trace**.
`SnsUserIntegrationEventPublisher` résout donc explicitement son résultat, et
`testItFailsWhenSnsRejectsThePublication` passe au rouge si on retire cette ligne — lui seul.

### L'attribut de message `type`

Il double le champ `type` du corps, et ce n'est pas redondant : **seuls les attributs sont lisibles
par une policy de filtrage d'abonnement**. Un futur abonné pourra donc ne recevoir que les types qui
l'intéressent, sans que ce service change.

> Ne pas le renommer sans relire le consommateur : côté SQS, les attributs arrivent dans les mêmes
> en-têtes que le `type` du sérialiseur standard de Messenger, qui porte un nom de classe. Le shop
> départage les deux par `class_exists()`.

## Configuration et IAM

| Variable | Développement | Production |
|---|---|---|
| `AWS_ENDPOINT` | `http://aws-local:4566` (LocalStack) | `https://sns.$AWS_REGION.amazonaws.com` |
| `USER_EVENTS_TOPIC_ARN` | ARN LocalStack, compte `000000000000` | l'ARN fourni par l'IaC |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | `test` / `test` | **absentes** |

**Aucune clé n'est passée au client dans le code**, et c'est leur absence en production qui laisse
la chaîne d'identifiants par défaut d'AsyncAws prendre le relais — **EKS Pod Identity**, où le seul
droit nécessaire est `sns:Publish` sur ce topic. C'est ce qui remplace toute idée de `ROLE_SERVICE`,
puisqu'il n'y a plus d'appel HTTP à autoriser.

> **Mais LocalStack exige des identifiants, et son refus accuse le mauvais coupable.** Sans
> `AWS_ACCESS_KEY_ID` ni `AWS_SECRET_ACCESS_KEY`, AsyncAws envoie une requête **non signée** ;
> LocalStack ne peut alors rattacher l'ARN à aucun compte et répond
> `InvalidParameter: Invalid parameter: TopicArn` — un message qui désigne l'ARN alors que celui-ci
> est parfaitement correct. Mesuré : le même appel passe dès que les deux variables existent, avec
> n'importe quelle valeur. Elles sont donc dans `.env.dist` à `test`, et **ne doivent pas être
> définies en production**, sous peine de masquer Pod Identity.
>
> AsyncAws les lit par `$_ENV` / `$_SERVER` / `getenv()`, dans cet ordre, donc `.env` suffit : rien
> à passer au client ni à déclarer dans `services.yaml`.

Seul le conteneur `worker` rejoint le réseau `en_shop_php_bus` : c'est lui qui publie. En production
il n'y a pas de réseau Docker à rejoindre — le bus est AWS, joint par l'egress normal.

**Brique `aws_local` éteinte, ce service démarre et répond quand même.** La publication échoue dans
le consommateur d'outbox, suit donc son retry (10 s → 5 min, six essais) et finit dans
`failed_domain_events`. Un compte créé pendant une panne du bus n'est pas perdu : il attend dans la
file d'échec, et `messenger:failed:retry` le publie.

## État

**Les trois faits sont publiés.** `PublishUserIntegrationEventHandler` réagit aux trois événements
du Domain depuis le consommateur d'outbox, et `ShopCustomerClientInterface` — le port dont l'unique
adaptateur journalisait — a disparu du dépôt avec ses deux handlers.

## Vérification

```bash
# Aucune suite ne touche SNS : un double en mémoire remplace l'adaptateur sous `when@test`.
make unit-suite s=infra.messaging
```

Bout en bout, les **trois** stacks démarrées (`aws_local`, `service_identity`, `service_shop`), et
le `worker` d'identity recréé après un `docker compose build` — c'est l'override qui le rattache au
réseau du bus, donc un conteneur plus ancien n'y est pas.

```bash
# 1. Une inscription cree le client, sans intervention
#    `preferences` est obligatoire : sans lui, 422 sur `preferences: should not be blank`.
ACCOUNT=$(curl -s -X POST localhost:20900/api/users/register -H 'Content-Type: application/json' \
  -d '{"email":"e2e@en-develop.fr","password":"userVenom1@","username":"e2e",
       "preferences":{"lang":"fr"}}' | python3 -c 'import sys,json; print(json.load(sys.stdin)["id"])')

cd ../service_shop && make bash-db
> db.customer.findOne({userAccountId: "<ACCOUNT>"})       # status 1, cree par le bus

# 2. Le meme `eventId` des deux cotes — la preuve que la cle de deduplication traverse
#    identity :
docker compose exec database psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
  -c "SELECT handler, event_id FROM processed_domain_event ORDER BY processed_at DESC LIMIT 1;"
#    shop :
> db.processed_event.find({}, {eventId: 1, handler: 1, source: 1}).sort({processedAt: -1}).limit(1)

# 3. Une suppression admin desactive le client
TOKEN=$(curl -s -X POST localhost:20900/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"venom@en-develop.fr","password":"userVenom1@"}' \
  | python3 -c 'import sys,json; print(json.load(sys.stdin)["accessToken"])')
curl -X DELETE "localhost:20900/api/users/$ACCOUNT" -H "Authorization: Bearer $TOKEN"   # 204
> db.customer.findOne({userAccountId: "<ACCOUNT>"})       # status 2, et `version` incremente :
                                                          # le meme document, pas une pierre tombale

# 4. Bus eteint : rien n'est perdu
cd ../aws_local && make down
curl -s -o /dev/null -w '%{http_code}\n' localhost:20900/health   # 200, le service vit seul
#   une inscription repond 201, et la publication part en retry (10 s -> 5 min, six essais)
docker compose logs worker | grep -E 'Could not contact|Sending for retry'
docker compose exec app bin/console messenger:stats                # failed_domain_events reste a 0
cd ../aws_local && make up                                         # le compte est rattrape seul
```

**Mesuré** : une publication en echec pendant trois minutes, puis la configuration corrigee — le
cinquieme essai est passe et le client a ete cree, sans intervention ni rejeu manuel. Le worker du
consommateur, lui, est reparti seul apres le retour de la brique, son budget de 50 essais Supervisor
n'etant pas epuise.

Ce que le consommateur fait du message, et ses propres scenarios (type inconnu, corps invalide,
commutativite) : [`../../service_shop/docs/integration_events.md`](../../service_shop/docs/integration_events.md).
