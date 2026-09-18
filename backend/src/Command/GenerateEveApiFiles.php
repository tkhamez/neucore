<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateEveApiFiles extends Command
{
    private OutputInterface $output;

    private \stdClass $definition;

    /**
     * @var string[]
     */
    private array $pathsInteger = [
        '{alliance_id}',
        '{attribute_id}',
        '{asteroid_belt_id}',
        '{category_id}',
        '{character_id}',
        '{constellation_id}',
        '{contract_id}',
        '{corporation_id}',
        '{destination_system_id}',
        '{effect_id}',
        '{fitting_id}',
        '{fleet_id}',
        '{graphic_id}',
        '{group_id}',
        '{item_id}',
        '{killmail_id}',
        '{market_group_id}',
        '{member_id}',
        '{moon_id}',
        '{origin_system_id}',
        '{planet_id}',
        '{region_id}',
        '{schematic_id}',
        '{squad_id}',
        '{star_id}',
        '{stargate_id}',
        '{station_id}',
        '{system_id}',
        '{task_id}',
        '{type_id}',
        '{war_id}',
        '{wing_id}',
    ];

    /**
     * @var string[]
     */
    private array $pathsHex = [
        '{killmail_hash}',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly HttpClientFactoryInterface $clientFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate-eve-api-files')
            ->setDescription(
                'Generates esi-paths-public.php, esi-paths-http-get.json, '
                . 'esi-paths-http-post.json and esi-rate-limits.php.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $esiHost = $this->config['eve']['esi_host'];
        $esiCompatibilityDate = $this->config['eve']['esi_compatibility_date'];
        $url = "$esiHost/meta/openapi.json?compatibility_date=$esiCompatibilityDate";

        $request = $this->clientFactory->createRequest('GET', $url);
        $client = $this->clientFactory->get();
        try {
            $response = $client->sendRequest($request);
        } catch (\Throwable $e) {
            $this->output->writeln('Error reading openapi.json: ' . $e->getMessage());
            return 1;
        }

        if ($response->getStatusCode() !== 200) {
            $this->output->writeln('Error reading openapi.json: HTTP ' . $response->getStatusCode());
            return 1;
        }

        $openapi = (string) $response->getBody();
        if (!$openapi) {
            $this->output->writeln('Error reading openapi.json.');
            return 1;
        }

        $definition = json_decode($openapi);
        if (!$definition instanceof \stdClass) {
            $this->output->writeln('Error decoding openapi.json.');
            return 1;
        }
        $this->definition = $definition;

        $this->generatePublicPaths();
        $this->generateGetPostPaths();
        $this->generateRateLimits();

        $output->writeln('All done.');

        return 0;
    }

    private function generatePublicPaths(): void
    {
        $public = [];
        foreach ($this->definition->paths as $path => $data) {
            if (
                (!isset($data->get) && !isset($data->post))
                || (isset($data->get->security))
                || (isset($data->post->security))
            ) {
                continue;
            }

            $public[] = $this->replacePlaceholders($path);
        }

        $this->writeFile(
            $this->config['esi']['files']['esi_paths_public'],
            $public,
            'php',
        );
    }

    private function generateGetPostPaths(): void
    {
        $get = [];
        $post = [];

        foreach ($this->definition->paths as $path => $data) {
            if (isset($data->get)) {
                $get[] = $path;
            }
            if (isset($data->post)) {
                $post[] = $path;
            }
        }

        $this->writeFile(
            $this->config['esi']['files']['esi_paths_http_get'],
            $get,
            'json',
        );

        $this->writeFile(
            $this->config['esi']['files']['esi_paths_http_post'],
            $post,
            'json',
        );
    }

    private function generateRateLimits(): void
    {
        $rateLimits = [];
        foreach ($this->definition->paths as $path => $data) {
            $path2 = $this->replacePlaceholders($path);
            foreach ($data as $method => $endpoint) {
                if (isset($endpoint->{'x-rate-limit'})) {
                    $rateLimits[$path2][strtolower($method)] = [
                        'group' => $endpoint->{'x-rate-limit'}->group,
                        'maxTokens' => $endpoint->{'x-rate-limit'}->{'max-tokens'},
                        'windowSize' => $endpoint->{'x-rate-limit'}->{'window-size'},
                    ];
                }
            }
        }

        $this->writeFile(
            $this->config['esi']['files']['esi_rate_limits'],
            $rateLimits,
            'php',
        );
    }

    /**
     * @see EsiClient::PATH_PATTERN
     */
    private function replacePlaceholders(string $path): string
    {
        // change paths to regular expression
        // e.g. /alliances/{alliance_id}/corporations/ => /alliances/[0-9]+/corporations/
        $path2 = str_replace($this->pathsInteger, '[0-9]+', $path);
        return str_replace($this->pathsHex, '[0-9a-fA-F]+', $path2);
    }

    /**
     * @param array<int|string, mixed> $content
     */
    private function writeFile(string $file, array $content, string $format): void
    {
        if ($format === 'php') {
            $data = "<?php\nreturn " . var_export($content, true) . ';';
        } else {
            $data = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($data === false) {
                $this->output->writeln("Failed to encode $file.");
                return;
            }
        }

        if (file_put_contents($file, $data) === false) {
            $this->output->writeln("Failed to write $file.");
        } else {
            $this->output->writeln("Wrote $file.");
        }
    }
}
