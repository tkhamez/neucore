<?php declare(strict_types=1);

namespace Neucore\Command\Traits;

use Neucore\Entity\SystemVariable;
use Neucore\Repository\SystemVariableRepository;

trait EsiRateLimited
{
    /**
     * @var SystemVariableRepository
     */
    protected $systemVariableRepository;

    protected function esiRateLimited(SystemVariableRepository $sysVarRepository): void
    {
        $this->systemVariableRepository = $sysVarRepository;
    }

    /**
     * Check ESI error limit and sleeps for max. 60 seconds if it is too low.
     */
    protected function checkErrorLimit(): void
    {
        $var = $this->systemVariableRepository->find(SystemVariable::ESI_ERROR_LIMIT);
        if ($var === null) {
            return;
        }

        $data = \json_decode($var->getValue());
        if (! $data instanceof \stdClass) {
            return;
        }

        if ($data->updated + $data->reset < time()) {
            return;
        }

        if ($data->remain < 10) {
            $sleep = min(60, $data->reset + time() - $data->updated);
            sleep($sleep);
        }
    }
}
