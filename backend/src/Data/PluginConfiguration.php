<?php

declare(strict_types=1);

namespace Neucore\Data;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * Common properties from plugin.yml (defaults) and database (current values).
 */
abstract class PluginConfiguration
{
    /**
     * Directory where the plugin.yml file is stored.
     *
     * Only from database but always set when the data from the file is read.
     *
     * @OA\Property()
     */
    public string $directoryName = '';

    /**
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/PluginConfigurationURL"))
     * @var PluginConfigurationURL[]
     */
    public array $URLs = [];

    /**
     * @OA\Property()
     */
    public string $textTop = '';

    /**
     * @OA\Property()
     */
    public string $textAccount = '';

    /**
     * @OA\Property()
     */
    public string $textRegister = '';

    /**
     * @OA\Property()
     */
    public string $textPending = '';

    /**
     * @OA\Property()
     */
    public string $configurationData = '';

    protected static function fromArrayCommon(self $obj, array $data): void
    {
        $obj->directoryName = $data['directoryName'] ?? '';

        $obj->URLs = [];
        foreach ($data['URLs'] ?? [] as $urlData) {
            $urlObj = new PluginConfigurationURL();
            $urlObj->url = $urlData['url'] ?? '';
            $urlObj->title = $urlData['title'] ?? '';
            $urlObj->target = $urlData['target'] ?? '';
            $obj->URLs[] = $urlObj;
        }

        $obj->textTop = $data['textTop'] ?? '';
        $obj->textAccount = $data['textAccount'] ?? '';
        $obj->textRegister = $data['textRegister'] ?? '';
        $obj->textPending = $data['textPending'] ?? '';
        $obj->configurationData = $data['configurationData'] ?? '';
    }

    protected function jsonSerializeCommon(array $result, bool $fullConfig, bool $includeBackendOnly): array
    {
        $result['directoryName'] = $this->directoryName;

        $result['URLs'] = array_map(function (PluginConfigurationURL $item) {
            return $item->jsonSerialize();
        }, ($this->URLs));

        $result['textTop'] = $this->textTop;
        $result['textAccount'] = $this->textAccount;
        $result['textRegister'] = $this->textRegister;
        $result['textPending'] = $this->textPending;
        $result['configurationData'] = $this->configurationData;

        if (!$includeBackendOnly) {
            unset($result['phpClass']);
            unset($result['psr4Prefix']);
            unset($result['psr4Path']);
        }

        if (!$fullConfig) {
            // keep only required API properties
            unset($result['phpClass']);
            unset($result['psr4Prefix']);
            unset($result['psr4Path']);
            unset($result['directoryName']);
        }

        return $result;
    }
}
