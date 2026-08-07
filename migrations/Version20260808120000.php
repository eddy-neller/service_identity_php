<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260808120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce email and username uniqueness on user at database level.';
    }

    public function up(Schema $schema): void
    {
        // Les index non uniques ne protégeaient de rien : UserUniquenessChecker fait un
        // check-then-insert en READ COMMITTED, donc deux inscriptions concurrentes sur le
        // même email passaient toutes les deux le SELECT (aucune ne voit la ligne non
        // commitée de l'autre) et inséraient toutes les deux. L'index unique est le seul
        // garde-fou réel ; le check applicatif reste en place pour renvoyer un 409 propre
        // dans le cas nominal, sans avoir à avorter la transaction.
        //
        // EmailAddress normalise en minuscules, un index unique simple suffit. Username ne
        // fait que trim() : l'unicité reste sensible à la casse, ce qui correspond au
        // comportement actuel de findByUsername().
        $this->addSql('DROP INDEX UserEmailIdx');
        $this->addSql('DROP INDEX UserUsernameIdx');

        $this->addSql('CREATE UNIQUE INDEX UserEmailUniq ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX UserUsernameUniq ON "user" (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UserUsernameUniq');
        $this->addSql('DROP INDEX UserEmailUniq');

        $this->addSql('CREATE INDEX UserEmailIdx ON "user" (email)');
        $this->addSql('CREATE INDEX UserUsernameIdx ON "user" (username)');
    }
}
