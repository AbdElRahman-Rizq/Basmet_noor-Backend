<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\License;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->restrictApiDocumentation();
        $this->enrichApiDocumentation();
    }

    /**
     * Scramble's `RestrictedDocsAccess` middleware allows the local environment and
     * otherwise requires this gate. Documentation stays a development feature and is
     * never exposed publicly in production.
     */
    private function restrictApiDocumentation(): void
    {
        Gate::define('viewApiDocs', function ($user = null): bool {
            return app()->environment(['local', 'testing']);
        });
    }

    /**
     * Add the shared conventions that cannot be inferred from the current routes:
     * an example response, the standard error envelope and endpoint metadata.
     *
     * Guarded because Scramble is a dev dependency and is absent from production
     * installs (`composer install --no-dev`).
     */
    private function enrichApiDocumentation(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::afterOpenApiGenerated(function (OpenApi $document): void {
            $document->info->setLicense(new License(name: 'MIT', identifier: 'MIT'));

            $errorEnvelope = $document->components->addSchema(
                'ErrorEnvelope',
                $this->errorEnvelopeSchema(),
            );

            $healthPath = collect($document->paths)->first(
                fn ($path) => $path->path === 'api/v1/health',
            );
            $operation = $healthPath?->operations['get'] ?? null;

            if ($operation === null) {
                return;
            }

            $operation->summary = 'Service health check';
            $operation->description = 'Reports application liveness. This endpoint does not touch the database, '
                .'so it keeps returning a healthy response while PostgreSQL is unavailable.';
            $operation->operationId = 'health.check';
            $operation->tags = ['System'];
            // Public endpoint: no authentication is required (and auth is not part of this phase).
            $operation->security = [];

            foreach ($operation->responses as $response) {
                if (! $response instanceof Response || (string) $response->code !== '200') {
                    continue;
                }

                $response->setDescription('The API process is running.');

                $response->addContent('application/json', new MediaType(
                    schema: $response->getContent('application/json'),
                    example: [
                        'success' => true,
                        'message' => 'Service is healthy',
                        'data' => [
                            'status' => 'ok',
                            'application' => 'basma-noor',
                            'version' => '1.0.0',
                        ],
                        'meta' => null,
                    ],
                ));
            }

            $operation->addResponse(
                (new Response(405))
                    ->setDescription('Wrong HTTP method. Returned with the standard error envelope.')
                    ->addContent('application/json', new MediaType(
                        schema: $errorEnvelope,
                        example: [
                            'success' => false,
                            'message' => 'Method not allowed.',
                            'error' => [
                                'code' => 'METHOD_NOT_ALLOWED',
                                'details' => (object) [],
                            ],
                        ],
                    )),
            );

            $operation->addResponse(
                (new Response('default'))
                    ->setDescription('Unexpected server error. Returned with the standard error envelope.')
                    ->addContent('application/json', new MediaType(
                        schema: $errorEnvelope,
                        example: [
                            'success' => false,
                            'message' => 'An unexpected error occurred.',
                            'error' => [
                                'code' => 'SERVER_ERROR',
                                'details' => (object) [],
                            ],
                        ],
                    )),
            );
        });
    }

    /**
     * Schema mirroring `App\Exceptions\ApiExceptionRenderer` output.
     */
    private function errorEnvelopeSchema(): Schema
    {
        return Schema::fromType(
            (new ObjectType)
                ->addProperty('success', (new BooleanType)->const(false))
                ->addProperty('message', (new StringType)->example('Validation failed'))
                ->addProperty('error', (new ObjectType)
                    ->addProperty('code', (new StringType)->example('VALIDATION_ERROR'))
                    ->addProperty('details', (new ObjectType)->additionalProperties(new MixedType)))
                ->setRequired(['success', 'message', 'error']),
        );
    }
}
