<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Neucore\Data\ServiceConfiguration;

class Version20221225185723 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->updateActive(true);
    }

    public function down(Schema $schema): void
    {
        $this->updateActive(false);
    }

    /**
     * @throws Exception
     */
    private function updateActive(bool $active): void
    {
        $serviceConfigurations = $this->connection->executeQuery('SELECT id, configuration FROM services');
        foreach ($serviceConfigurations->fetchAllAssociative() as $data) {
            $configuration = json_decode((string)$data['configuration'], true);
            if (!is_array($configuration)) {
                continue;
            }

            $sc = ServiceConfiguration::fromArray($configuration);
            $configData = $sc->jsonSerialize();
            if ($active) {
                $configData['active'] = true;
            } else {
                unset($configData['active']);
            }

            $this->connection->executeQuery(
                'UPDATE services SET configuration = ? WHERE id = ?',
                [json_encode($configData), $data['id']]
            );
        }
    }
}
