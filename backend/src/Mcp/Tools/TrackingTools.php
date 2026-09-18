<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Neucore\Entity\CorporationMember;
use Neucore\Factory\RepositoryFactory;
use Neucore\Mcp\ResponseBuilder;

class TrackingTools
{
    public function __construct(
        private readonly RepositoryFactory $repositoryFactory,
        private readonly ResponseBuilder $responseBuilder,
    ) {}

    #[McpTool(
        name: 'get_member_tracking',
        description: 'Get member tracking data for a corporation.',
    )]
    /**
     * @param int $corporationId The corporation ID
     */
    public function getMemberTracking(
        int $corporationId,
        #[Schema(
            definition: [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'integer', 'description' => 'Maximum age of active members in days'],
                    'inactive' => ['type' => 'integer', 'description' => 'Maximum age of inactive members in days'],
                    'account' => ['type' => 'boolean', 'description' => 'Filter by "belongs to a player account"'],
                    'tokenStatus' => [
                        'type' => 'integer',
                        'enum' => [1, 2, 3],
                        'description' => '1=valid, 2=invalid, 3=none',
                    ],
                    'tokenChanged' => ['type' => 'integer', 'description' => 'Maximum days since token status changed'],
                    'mailCount' => ['type' => 'integer', 'description' => 'Minimum number of missing character mails'],
                ],
            ],
            description: 'Filter criteria for corporation member tracking',
        )]
        array $filter = [],
    ): array {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if ($corporation === null) {
            return $this->responseBuilder->error(0, 'Corporation not found');
        }

        $repository = $this->repositoryFactory->getCorporationMemberRepository();
        $repository->resetCriteria();

        if (!empty($filter['active'])) {
            $repository->setActive((int) $filter['active']);
        }

        if (!empty($filter['inactive'])) {
            $repository->setInactive((int) $filter['inactive']);
        }

        if (array_key_exists('account', $filter) && $filter['account'] !== null) {
            $repository->setAccount((bool) $filter['account']);
        }

        if (!empty($filter['tokenStatus'])) {
            $repository->setTokenStatus((int) $filter['tokenStatus']);
        }

        if (!empty($filter['tokenChanged'])) {
            $repository->setTokenChanged((int) $filter['tokenChanged']);
        }

        if (!empty($filter['mailCount'])) {
            $repository->setMailCount((int) $filter['mailCount']);
        }

        $members = $repository->findMatching($corporationId);

        return $this->responseBuilder->success(
            array_map(fn(CorporationMember $member) => $member->jsonSerialize(), $members),
        );
    }
}
