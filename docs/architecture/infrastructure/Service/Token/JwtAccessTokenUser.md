# JwtAccessTokenUser

Fichier documenté : `infrastructure/src/Service/Token/JwtAccessTokenUser.php`.

## Rôle

`JwtAccessTokenUser` est un adaptateur technique minimal entre l'agrégat
`Domain\User\Model\User` et Lexik JWT.

`LexikJwtAccessTokenProvider` émet l'access token à partir de l'utilisateur
Domain. Or `JWTTokenManagerInterface::createFromPayload()` impose un objet
Symfony `UserInterface` afin de lire l'identifiant utilisateur et les rôles.
Le Domain ne doit pas dépendre de Symfony : cet adaptateur porte donc cette
contrainte de framework exclusivement dans Infrastructure.

Il ne contient que les données nécessaires à la signature :

- l'UUID, utilisé par Lexik comme claim d'identité (`user_id_claim: sub`) ;
- les rôles ;
- les méthodes requises par `UserInterface`.

`LexikJwtAccessTokenProvider` ajoute également `auth_version`, valeur opaque
vérifiée dans Redis à chaque requête authentifiée. Le contrat complet est
documenté dans [`docs/JWT_authentication.md`](../../../../JWT_authentication.md).

## Pourquoi ne pas réutiliser l'entité Doctrine User ?

L'émission d'un token part déjà d'un agrégat Domain chargé par le cas d'usage.
Recharger `App\Infrastructure\Entity\User\User` uniquement pour satisfaire
Lexik créerait un accès base de données inutile et couplerait le provider JWT
au modèle de persistance.

L'adaptateur évite ces deux effets : il ne fait aucun I/O et ne transporte que
les claims nécessaires à la bibliothèque.

## Pourquoi pas une closure ou une classe anonyme ?

Une closure ne peut pas convenir : Lexik attend une instance de `UserInterface`,
pas une fonction. Une classe anonyme pourrait techniquement implémenter cette
interface dans `LexikJwtAccessTokenProvider`, mais elle rendrait le contrat
moins explicite, moins testable et dérogerait à la convention du projet d'une
classe par fichier.

`JwtAccessTokenUser` est donc un anti-corruption layer volontairement petit et
localisé, pas un second modèle utilisateur.

## Limites

Cette classe ne vérifie pas un mot de passe, ne charge aucun utilisateur et ne
sert jamais à authentifier une requête entrante. La vérification des JWT et le
chargement de l'identité restent confiés au firewall Lexik/Symfony. La gestion
de session combine les refresh tokens et la vérification Redis de `auth_version`.
