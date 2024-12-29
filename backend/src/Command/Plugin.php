<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Command\Traits\Argv;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Plugin\Core\Output;
use Neucore\Plugin\Exception;
use Neucore\Plugin\GeneralInterface;
use Neucore\Service\PluginService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Plugin extends Command
{
    use Argv;

    public function __construct(
        private RepositoryFactory $repositoryFactory,
        private PluginService $pluginService,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin')
            ->setDescription('Executes GeneralInterface::command() from plugins.')
            ->setHelp(
                'In addition to any number of arguments this command accepts any number of long options ' .
                'with any name, but not several with the same name.',
            )
            ->addArgument('id', InputArgument::REQUIRED, 'The plugin ID.')
            ->addArgument('args', InputArgument::IS_ARRAY, 'Optional additional arguments.');

        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginId = intval($input->getArgument('id'));
        $arguments = $input->getArgument('args');

        $options = [];
        $optionName = null;
        $argv = $this->argv !== null ? $this->argv : ($_SERVER['argv'] ?? []);
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $optionName = substr($arg, 2);
                $options[$optionName] = '';
            } elseif ($optionName !== null) {
                $options[$optionName] = $arg;
                $optionName = null;
            }
        }

        $plugin = $this->repositoryFactory->getPluginRepository()->find($pluginId);
        if (!$plugin?->getConfigurationDatabase()?->active) {
            $output->writeln("Plugin $pluginId not found or not active.");
            return 0;
        }

        $implementation = $this->pluginService->getPluginImplementation($plugin);
        if (!$implementation instanceof GeneralInterface) {
            $output->writeln("Plugin $pluginId implementation not found or does not implement GeneralInterface.");
            return 0;
        }

        try {
            $implementation->command($arguments, $options, new Output($output));
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [Context::EXCEPTION => $e]);
        }

        return 0;
    }
}
