<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Controller\BaseController;
use Neucore\Service\AppAuth;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


#[OA\Tag(name: 'Application', description: 'API for applications.')]
#[OA\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    description: 'The API key is a base64-encoded string containing the app ID and secret separated by a colon',
    scheme: 'bearer'
)]
class ApplicationController extends BaseController
{
    #[OA\Get(
        path: '/app/v1/show',
        operationId: 'showV1',
        description: 'Needs role: app',
        summary: 'Show app information.',
        security: [['BearerAuth' => []]],
        tags: ['Application'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The app information',
                content: new OA\JsonContent(ref: '#/components/schemas/App')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(
                response: '500',
                description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
    )]
    public function showV1(ServerRequestInterface $request, AppAuth $appAuthService): ResponseInterface
    {
        return $this->withJson($appAuthService->getApp($request));
    }
}
