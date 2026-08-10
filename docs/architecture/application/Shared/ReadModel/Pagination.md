# Pagination applicative

`Pagination` (`src/Application/Shared/ReadModel/Pagination.php`) est une
valeur applicative immuable. Elle regroupe la normalisation de `page` et
`itemsPerPage` pour les queries de collection.

Elle vit dans la couche Application, et non dans le Domain, car la pagination
ne représente pas un concept métier ni un invariant d'un agrégat. C'est une
politique de lecture d'un cas d'usage : `fromRaw()` et `fromValues()` ramènent
une valeur absente ou invalide à `page = 1` et `itemsPerPage = 30`.

## Frontière des couches

- La Presentation transporte les scalaires reçus par HTTP.
- La Command ou Query transporte aussi ces primitives : elle ne contient pas
  une instance de `Pagination`.
- Le handler construit `Pagination`, puis transmet les entiers normalisés au
  Port de repository.
- Le Domain reste réservé aux Value Objects métier, tels que `UserId`,
  `Email` ou `Money`.

Ne déplacer `Pagination` vers le Domain que si la pagination devenait un
concept métier avec des invariants propres à un agrégat, ce qui n'est pas le
cas. À l'inverse, une règle qui ne servirait qu'à décoder ou présenter HTTP
appartiendrait à la Presentation, et non à cette valeur applicative.
