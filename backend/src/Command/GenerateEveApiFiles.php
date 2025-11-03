<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateEveApiFiles extends Command
{
    private OutputInterface $output;

    private string $esiCompatibilityDate = '';

    private string $esiHost = '';

    public function __construct(private readonly Config $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate-eve-api-files')
            ->setDescription('Generates esi-paths-public.php.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->esiCompatibilityDate = $this->config['eve']['esi_compatibility_date'];
        $this->esiHost = $this->config['eve']['esi_host'];

        $this->publicEsiPaths();

        $output->writeln('All done.');

        return 0;
    }

    private function publicEsiPaths(): void
    {
        $openapi = file_get_contents(
            "$this->esiHost/meta/openapi.json?compatibility_date=$this->esiCompatibilityDate"
        );
        if (!$openapi) {
            $this->output->writeln("Error reading openapi.json.");
            return;
        }

        $def = json_decode($openapi);

        $public = [];
        foreach ($def->paths as $path => $data) {
            if (
                (!isset($data->get) && !isset($data->post)) ||
                (isset($data->get->security)) ||
                (isset($data->post->security))
            ) {
                continue;
            }

            // change paths to regular expression
            // e.g. /alliances/{alliance_id}/corporations/
            //   =>  /alliances/[0-9]+/corporations/
            $regExp = str_replace([
                '{alliance_id}',
                '{character_id}',
                '{contract_id}',
                '{region_id}',
                '{corporation_id}',
                '{attribute_id}',
                '{type_id}',
                '{item_id}',
                '{killmail_id}',
                '{market_group_id}',
                '{task_id}',
                '{asteroid_belt_id}',
                '{category_id}',
                '{constellation_id}',
                '{graphic_id}',
                '{group_id}',
                '{moon_id}',
                '{planet_id}',
                '{schematic_id}',
                '{stargate_id}',
                '{star_id}',
                '{war_id}',
                '{effect_id}',
                '{station_id}',
                '{system_id}',
                '{fleet_id}',
                '{wing_id}',
                '{origin_system_id}',
                '{destination_system_id}',
            ], '[0-9]+', $path);

            // It looks like this is a HEX value
            $regExp2 = str_replace('{killmail_hash}', '[0-9a-fA-F]+', $regExp);

            $public[] = $regExp2;
        }

        $result = var_export($public, true);
        file_put_contents(
            __DIR__ . '/../config/esi-paths-public.php',
            "<?php\nreturn " . $result . ';',
        );

        $this->output->writeln("Wrote config/esi-paths-public.php.");
    }
}
