# DoctrineUserRepository

Fichier documenté : `src/Infrastructure/Persistence/Doctrine/User/DoctrineUserRepository.php`.

## Rôle

`DoctrineUserRepository` est l'adaptateur Doctrine du port applicatif
`UserRepositoryInterface`. Il convertit les entités persistées via `UserMapper`
et expose des agrégats `Domain\User\Model\User` au reste de l'application.

Il isole Doctrine, son `EntityManager` et ses exceptions du Domain et de
l'Application. Les appels aux méthodes du repository viennent des handlers
applicatifs ; la Presentation y accède uniquement en passant par les bus CQRS.

## Création et mise à jour

`nextIdentity()` délègue la génération de l'identifiant à
`UuidGeneratorInterface`. Un agrégat qui vient de recevoir cet identifiant est
persisté avec `add()` : le mapper crée une nouvelle entité Doctrine, sans lecture
préalable.

`save()` est réservé à un agrégat existant préalablement chargé par une méthode
`find*()`. Il récupère alors l'entité persistée et la transmet au mapper afin de
conserver l'identité de ses champs objets immuables. Dans le chemin nominal, la
recherche est satisfaite par l'identity map Doctrine, puisque le même
`EntityManager` a chargé l'entité.

Ne pas faire transiter une création par `save()` : cela ajouterait une lecture
inutile. Inversement, ne pas utiliser `add()` pour un agrégat existant, car le
mapper doit recevoir l'entité managée pour n'écrire que les champs mutables.

## Unicité sous concurrence

Les vérifications métier peuvent constater qu'un username ou un e-mail est
disponible, mais deux requêtes concurrentes peuvent faire ce même constat avant
qu'aucune n'écrive. Les contraintes uniques de la base restent donc l'autorité
finale.

Après chaque écriture, le repository intercepte
`UniqueConstraintViolationException` et la traduit en
`EmailAlreadyUsedException` ou `UsernameAlreadyUsedException`. L'Application et
la Presentation conservent ainsi une erreur métier cohérente au lieu d'exposer
une exception Doctrine et une réponse 500.

Cette traduction dépend des noms de contraintes `UserEmailUniq` et
`UserUsernameUniq`. PostgreSQL renvoyant les identifiants non quotés en
minuscules, la comparaison s'effectue sur le message normalisé en minuscules.
Tout renommage doit modifier ensemble l'entité Doctrine, la migration et ce
mapping d'erreur.

## Lecture de collection

`list()` applique uniquement les filtres username/e-mail et les champs de tri
autorisés par le port. Le tri se termine toujours par `u.id ASC` pour obtenir un
ordre total : deux utilisateurs ayant la même valeur de tri ne peuvent pas
changer arbitrairement de page.

La requête ne fait pas de fetch-join de collection. Le `Paginator` est donc créé
avec `fetchJoinCollection: false` et les output walkers sont désactivés afin de
réduire le coût du comptage. Si cette requête acquiert un `HAVING`, un tri sur une
expression calculée ou un fetch-join to-many, réévaluer ce réglage et la
pagination avant de le conserver.

## Limites

- Les recherches par token délèguent le filtrage JSON au repository Doctrine
  spécialisé ; le port ne révèle pas ce détail de stockage.
- `delete()` ne traduit pas de violation d'unicité : il ne crée ni ne modifie de
  valeur soumise à ces contraintes.
- Le repository ne porte aucune règle métier ; il traduit seulement les erreurs
  d'intégrité nécessaires au contrat du port.
