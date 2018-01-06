<?php
namespace Brave\Core;

use DI\Bridge\Slim\App;
use DI\ContainerBuilder;

/**
 * Extends DI\Bridge\Slim to configure it.
 */
class SlimApp extends App
{
    private $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;

        parent::__construct();
    }

    protected function configureContainer(ContainerBuilder $builder)
    {
        $builder->addDefinitions($this->settings);
    }
}
