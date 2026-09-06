<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\McpToken;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Util\Crypto;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mcp:create-token',
    description: 'Create a new MCP token',
)]
class CreateMcpToken extends Command
{
    public function __construct(
        private RepositoryFactory $repositoryFactory,
        private ObjectManager $objectManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Token name')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Role to assign (can be used multiple times)', [])
            ->addOption('secret', 's', InputOption::VALUE_OPTIONAL, 'Custom secret (generated if not provided)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $roles = $input->getOption('role');
        $customSecret = $input->getOption('secret');

        // Check if token with this name already exists
        $existing = $this->repositoryFactory->getMcpTokenRepository()->findByName($name);
        if ($existing !== null) {
            $io->error("Token with name '$name' already exists.");
            return Command::FAILURE;
        }

        // Generate or use custom secret
        $secret = $customSecret ?? bin2hex(random_bytes(32));
        $secretHash = password_hash($secret, Crypto::PASSWORD_HASH);

        // Create token
        $token = new McpToken();
        $token->setName($name);
        $token->setSecret($secretHash);

        // Assign roles
        foreach ($roles as $roleName) {
            $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
            if ($role === null) {
                $io->error("Role '$roleName' not found.");
                return Command::FAILURE;
            }
            $token->addRole($role);
        }

        // If no roles specified, assign base 'mcp' role
        if (empty($roles)) {
            $mcpRole = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => Role::MCP]);
            if ($mcpRole !== null) {
                $token->addRole($mcpRole);
            }
        }

        $this->objectManager->persist($token);
        $this->objectManager->flush();

        $io->success("MCP token created successfully.");
        $io->text("ID: {$token->getId()}");
        $io->text("Name: {$token->getName()}");
        $io->text("Secret: $secret");
        $io->warning('Store the secret securely - it will not be shown again.');
        $io->text('');
        $io->text('Use the following Authorization header format:');
        $io->text('Authorization: Bearer ' . base64_encode($token->getId() . ':' . $secret));

        return Command::SUCCESS;
    }
}
