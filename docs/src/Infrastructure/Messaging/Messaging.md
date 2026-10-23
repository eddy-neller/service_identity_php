# `Infrastructure/Messaging/` — la frontière de publication

Sources :

- [`src/Infrastructure/Messaging/UserIntegrationEvent.php`](../../../../src/Infrastructure/Messaging/UserIntegrationEvent.php) — le contrat ;
- [`src/Infrastructure/Messaging/UserIntegrationEventPublisherInterface.php`](../../../../src/Infrastructure/Messaging/UserIntegrationEventPublisherInterface.php) — ce que le handler injecte ;
- [`src/Infrastructure/Messaging/IntegrationBusUnavailableException.php`](../../../../src/Infrastructure/Messaging/IntegrationBusUnavailableException.php) — « le bus est injoignable, attendre a un sens » ;
- [`src/Infrastructure/Messaging/Sns/SnsUserIntegrationEventPublisher.php`](../../../../src/Infrastructure/Messaging/Sns/SnsUserIntegrationEventPublisher.php) — l'adaptateur AWS.

> **Une doc pour quatre fichiers**, là où la convention en demande une par fichier : ils ne se
> comprennent qu'ensemble, et les séparer disperserait des décisions qui se répondent. Même choix que
> `service_shop` pour son inbox.
>
> Le **contrat**, son évolution, le chemin complet d'une publication et la correspondance IAM :
> [`docs/integration_events.md`](../../../integration_events.md). Ce document-ci explique le **code** :
> pourquoi ces trois classes, et ce qui casse si on les modifie mal.

## Les trois classes

| Classe | Rôle | Ce qu'elle ignore |
|---|---|---|
| `UserIntegrationEvent` | porte le contrat et le rend en tableau | tout transport, tout Domain Event |
| `UserIntegrationEventPublisherInterface` | ce que le handler injecte | toute technologie |
| `SnsUserIntegrationEventPublisher` | parle à AWS | la forme du contrat |
| `IntegrationBusUnavailableException` | dit qu'attendre a un sens | toute technologie, toute politique de retry |

Le découpage n'est pas décoratif : **la forme du message et le moyen de l'envoyer changent pour des
raisons différentes**, et à des rythmes différents.

---

## 1. `UserIntegrationEvent` — le contrat vit ici, et nulle part ailleurs

### Les clés JSON ne sont pas les noms de propriétés, et c'est volontaire

```php
public function toContract(): array
{
    return [
        'id' => $this->eventId,                                        // eventId -> id
        'type' => $this->type,
        'version' => self::VERSION,
        'occurredAt' => $this->occurredAt->format(DateTimeInterface::ATOM),
        'data' => ['userAccountId' => $this->userAccountId],           // descend dans data
    ];
}
```

Les propriétés PHP peuvent être renommées librement ; **les clés JSON non**. Une sérialisation
automatique — un `jsonSerialize()` naïf, le serializer Symfony, un `get_object_vars()` — lierait les
deux : renommer `$eventId` en `$id` au cours d'une refonte casserait tous les consommateurs sans
qu'un seul test local ne rougisse.

La traduction est donc **écrite à la main**. Elle est visible en diff, et c'est tout son intérêt.

### L'enveloppe et la charge utile sont séparées

`id`, `type`, `version`, `occurredAt` forment la **colonne vertébrale** : stable d'une version à
l'autre, lisible même pour un type qu'on ne connaît pas. `data` porte ce qui est propre au type.

C'est ce découpage qui permet au consommateur d'extraire `id` et `type` d'un message qu'il ne saura
pas traiter, pour le journaliser utilement au lieu de le jeter en silence.

### Des constantes, pas une enum

Une `enum UserIntegrationEventType` serait mieux typée. Elle n'est pas là parce que **ces valeurs
sortent du processus** : c'est leur forme textuelle qui est le contrat. Une enum les rendrait
indirectes (`$case->value` partout) sans rien garantir de plus à la frontière — la validation utile
est celle du consommateur, qui reçoit du JSON et ne connaît aucune enum.

Le prix assumé : `$type` est un `string` libre. Le `match` du handler ne produit que les trois types
du contrat, et `testTheThreeContractTypesAreStable` les fige.

### `toContract()` est sur le DTO, pas dans l'adaptateur

La **forme** appartient au contrat, l'**encodage** au transport. Un second transport — un autre bus,
un webhook, un export — publierait le même corps sans le redéfinir. Mettre la forme dans l'adaptateur
SNS reviendrait à dire « le contrat, c'est ce que SNS transporte », ce qui est faux.

### Ce que la classe ne fait pas

- **Elle ne connaît aucun Domain Event.** Pas de `fromDomainEvent(UserRegisteredEvent $e)`, bien que
  ce soit plus court : la traduction événement → type vit dans le handler, là où les trois événements
  sont connus et où un `match` rend la correspondance lisible.
- **Elle ne valide rien.** Un DTO de frontière qui refuserait ses propres entrées donnerait une
  fausse impression de sûreté : la validation qui compte est celle du lecteur, à l'autre bout.
- **Elle n'implémente pas `JsonSerializable`.** Rendre un tableau garde l'encodage explicite chez
  l'appelant, et rend la méthode testable sans décoder du JSON.

---

## 2. L'interface — contrat interne, pas Port applicatif

Le plan d'origine disait « Port dans `Application/` ». C'est `src/Infrastructure/AGENTS.md` qui
tranche :

> Une interface dont **aucun** use case / service applicatif n'est consommateur n'est pas un Port :
> elle reste dans `src/Infrastructure/`, à côté de son implémentation.

Le test est : **qui l'injecte ?** Un handler de Domain Event, qui vit dans `Infrastructure/`. Aucun
cas d'usage. C'est donc le troisième contrat interne du même genre, à côté de
`UserNotifierInterface` et `DomainEventLedgerInterface`, et il figure dans la même table.

Devenue Port, cette interface aurait donné à `Application/` une abstraction que rien dans
`Application/` n'utilise — et la règle « tout Port a son consommateur applicatif » aurait eu sa
première exception, donc n'en serait plus une.

### Pourquoi une interface, puisqu'il n'y a qu'un adaptateur réel

Il y en a **deux** : l'adaptateur SNS et le double en mémoire de l'environnement de test. C'est ce
qui rend l'interface nécessaire et pas seulement élégante — sans elle, aucune suite ne tournerait
sans LocalStack et son jeton, que la CI n'a pas.

### Les trois obligations qu'elle impose

1. **Publier est un effet externe.** L'appel ne peut pas rejoindre la transaction du handler, et ne
   doit pas être placé dans un `transactional()` : ce serait tenir une transaction PostgreSQL ouverte
   — donc des verrous — pendant un aller-retour réseau.
2. **L'implémentation doit lever si la publication n'a pas abouti.** Le contrat du handler (garde →
   effet → marquage) ne tient que si l'effet sait échouer bruyamment. Un adaptateur qui attraperait
   ses propres erreurs « pour ne pas casser le worker » détruirait la garantie entière.
3. **Un seul paramètre.** `publish(UserIntegrationEvent $event)` et non quatre scalaires dont trois
   `string` dans un ordre arbitraire, qui s'inversent un jour sans que rien ne le détecte.
4. **Dire si attendre a un sens.** Un bus injoignable lève `IntegrationBusUnavailableException`, tout
   autre échec autre chose. C'est la seule distinction dont le handler a besoin pour choisir entre
   « rejouer jusqu'au retour du bus » et « six essais, puis la file d'échec ». L'exception ne nomme
   aucune technologie : un second adaptateur la lèverait pour ses propres raisons.

---

## 3. L'adaptateur SNS — et la ligne qui empêche une perte silencieuse

### `resolve()` n'est pas optionnel

**AsyncAws est asynchrone par son nom et par son comportement.** `publish()` ne fait aucun appel
réseau : il construit une requête et rend un `Result` paresseux. La requête part quand quelqu'un lit
le résultat — ou, à défaut, au destructeur, où l'exception est avalée.

Sans `resolve()`, la chaîne se dégrade **sans une seule erreur** :

```text
publish()           ──▶  rien ne part encore
markProcessed()     ──▶  l'evenement est note « traite »
ack du message      ──▶  l'outbox est videe
   ...plus tard, au GC :  la requete echoue, personne ne le saura jamais
```

L'événement est définitivement perdu, le ledger affirme l'avoir traité, aucun log ne le mentionne.

`resolve()` bloque jusqu'à la réponse et lève sur erreur HTTP ou réseau. C'est ce qui rend le `void`
de l'interface digne de confiance : **si la méthode revient, le message est chez SNS.**

C'est testé par la négative — `testItFailsWhenSnsRejectsThePublication` fabrique un résultat en
échec et attend une exception. **Retirer `resolve()` fait tomber ce test, et lui seul.**

### Traduire l'indisponibilité, sans rien avaler

Trois échecs deviennent `IntegrationBusUnavailableException`, la cause d'origine gardée en `previous` :

| Échec AsyncAws | Ce qu'il signifie |
|---|---|
| `NetworkException` | hôte introuvable, connexion refusée : la brique `aws_local` éteinte, le réseau coupé |
| `ServerException` | SNS répond en 5xx |
| `ClientException` en 429, ou en 400 avec un code d'étranglement (`Throttling`…) | trop de requêtes ; SNS utilise la seconde forme |

Quand l'un d'eux arrive ici, **AsyncAws l'a déjà réessayé** en interne (`AwsRetryStrategy`, quelques
essais rapprochés) : ce n'est plus un hoquet, c'est une indisponibilité.

Tout le reste remonte **tel quel**, et c'est voulu : une requête que SNS refuse (`InvalidParameter`,
droits, topic inexistant) ne passera jamais en attendant. Rejouée sans fin, elle n'atteindrait jamais
la file d'échec, et personne ne la verrait. `testItLetsARejectedRequestThroughUnchanged` tient cette
frontière.

### Les deux drapeaux de `json_encode()`

- **`JSON_THROW_ON_ERROR`** : sans lui, un échec d'encodage rend `false` et on publierait une chaîne
  vide. Avec, ça lève, donc le handler ne marque pas, donc Messenger rejoue.
- **`JSON_UNESCAPED_SLASHES`** : cosmétique aujourd'hui, aucun champ ne contenant de `/`. Le jour où
  l'un en contiendrait, PHP écrirait `\/` et la fixture du consommateur ne correspondrait plus octet
  pour octet. Un drapeau qui coûte zéro et évite une divergence de forme.

### L'attribut de message `type`

Il double le champ `type` du corps, et ce n'est pas redondant : **seuls les attributs sont lisibles
par une policy de filtrage d'abonnement.** Un futur abonné pourra ne recevoir que les suppressions
sans que ce service change une ligne.

> **Ne pas le renommer sans relire le consommateur.** Côté SQS, les attributs de message arrivent
> dans les **mêmes en-têtes** que le `type` du sérialiseur standard de Messenger, qui porte un nom de
> classe. Le shop départage les deux par `class_exists()` : un attribut nommé autrement, ou portant
> un nom de classe, casserait ce départage — dans l'autre dépôt, six mois plus tard.

### Les deux arguments du constructeur

- **`SnsClient` est injecté**, pas construit ici. Le construire sur place (`new SnsClient([...])`)
  rendrait l'adaptateur intestable sans réseau et mélangerait configuration et logique. Il est
  enregistré dans `config/services.yaml`, où vivent déjà les paramètres.
- **`$topicArn` est une chaîne**, pas un Value Object : il n'y a aucun invariant à faire respecter,
  et AWS le valide mieux qu'une regex locale.

### Ce que l'adaptateur ne fait pas

- **Il n'avale rien.** Son seul `try/catch` traduit trois échecs en un autre, et relance dans tous
  les cas : aucun chemin ne rend au handler un succès qu'il n'a pas eu. Un `catch` suivi d'un simple
  `$logger->error()` serait la faute que l'interface interdit.
- **Il ne journalise pas les succès.** Le worker Messenger trace déjà chaque message traité et le
  handler ajoute sa ligne ; un troisième log au même instant n'apprendrait rien.
- **Il ne connaît aucune clé.** Ni dans le fichier, ni dans `services.yaml`. La chaîne
  d'identifiants par défaut d'AsyncAws s'en charge — EKS Pod Identity en production, où le seul droit
  nécessaire est `sns:Publish` sur ce topic. En développement, LocalStack exige en revanche des
  identifiants *quelconques* dans l'environnement : sans eux la requête part non signée et son refus
  accuse l'ARN à tort. Détail dans
  [`docs/integration_events.md`](../../../integration_events.md) — mais rien à changer **ici**,
  l'adaptateur n'en voit jamais la couleur.
- **Il ne crée pas le topic.** Celui-ci appartient à la brique `aws_local` en développement et à
  l'IaC en production. Le créer ici exigerait un droit IAM de création pour une ressource qu'on ne
  possède pas — même raisonnement que l'`auto_setup=false` du transport SQS du consommateur.

---

## Points de vigilance lors d'une modification

- **Changer la forme de `toContract()` ne casse aucun test de ce dépôt** si l'on met à jour
  `UserIntegrationEventTest` dans le même geste. Les deux rives du contrat ne sont comparées par
  rien : seule la vérification de bout en bout attrape la rupture.
- **Ne jamais retirer `resolve()`**, ni le remplacer par une lecture partielle du résultat. Un
  événement perdu sans trace est le mode de défaillance que cette ligne existe pour empêcher.
- **Ne jamais avaler l'échec de publication**, ni dans l'adaptateur ni dans le handler. On peut le
  traduire, jamais le faire disparaître : l'exception est ce qui empêche le marquage, donc ce qui
  garantit le rejeu.
- **Ne pas élargir la traduction en indisponibilité.** Traduire une erreur qui ne se résorbera pas
  seule (une requête refusée, un bug) la ferait rejouer sans limite, sans qu'elle atteigne jamais la
  file d'échec. Réseau, 5xx et throttling, rien d'autre.
- **Ne pas déplacer l'interface dans `Application/`** : aucun cas d'usage ne l'injecte.
- **Ne pas incrémenter `VERSION` pour un ajout de champ** — règle détaillée dans
  [`docs/integration_events.md`](../../../integration_events.md).
- **Ne pas renommer l'attribut de message `type`** sans relire le sérialiseur du consommateur.

## Ce qui le vérifie

Suite `infra.messaging` (`make unit-suite s=infra.messaging`), sans réseau ni conteneur :

- `UserIntegrationEventTest` — le contrat rendu à l'identique, la date en ISO-8601 avec décalage
  explicite (vérifiée sur une heure de Paris, donc `+02:00`), et les trois types figés avec la
  version ;
- `SnsUserIntegrationEventPublisherTest` — l'ARN visé, le corps publié comparé **caractère pour
  caractère** au contrat, l'attribut de message, l'échec de publication qui remonte, le bus
  injoignable sous ses quatre formes (réseau, 5xx, 429, throttling en 400) et la requête refusée qui,
  elle, n'est **pas** traduite.

Hors de cette suite, `PublishUserIntegrationEventHandlerTest` (`infra.symfony.messenger.event`) tient
l'autre moitié : bus injoignable → échec récupérable, tout autre échec → remonté tel quel, jamais
récupérable. Chacun de ces cas a été vu rougir en retirant ce qu'il protège.

Aucune de ces suites ne touche SNS : en test, un double en mémoire remplace l'adaptateur
(`config/services.yaml`, `when@test`).
