<?php

namespace App\Tests\Unit\Project;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Project\ApiResource\ProjectRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Image;

final class ProjectRequestMetadataTest extends TestCase
{
    public function testTheImageConstraintsHardCeilingIsDecimalMegabytes(): void
    {
        $property = new \ReflectionProperty(ProjectRequest::class, 'image');
        $attributes = $property->getAttributes(Image::class);

        self::assertCount(1, $attributes, 'ProjectRequest::$image must carry exactly one Assert\Image constraint.');

        $arguments = $attributes[0]->getArguments();
        self::assertArrayHasKey('maxSize', $arguments, 'The Assert\Image constraint must declare maxSize explicitly.');

        $maxSize = $arguments['maxSize'];
        self::assertIsString($maxSize);
        self::assertSame(
            '30M',
            $maxSize,
            'The hard ceiling must be decimal megabytes ("30M"), matching the decimal-MB arithmetic '
            .'ProjectCreationLimitsValidator uses for the configurable free-tier limit. A binary suffix '
            .'("30Mi") disagrees with that arithmetic by about 4.86%.',
        );

        $constraint = $attributes[0]->newInstance();
        self::assertInstanceOf(Image::class, $constraint);
        self::assertSame(30_000_000, $constraint->maxSize, 'decimal 30M must normalize to exactly 30,000,000 bytes.');
    }

    public function testTheOpenApiRequestBodyDescriptionStatesTheConfigurableLimitAndTheHardCeiling(): void
    {
        $class = new \ReflectionClass(ProjectRequest::class);
        $resourceAttributes = $class->getAttributes(ApiResource::class);
        self::assertCount(1, $resourceAttributes);

        $resource = $resourceAttributes[0]->newInstance();
        self::assertInstanceOf(ApiResource::class, $resource);

        $operations = $resource->getOperations();
        self::assertNotNull($operations, 'ProjectRequest must declare its operations.');

        $createOperation = null;
        foreach ($operations as $operation) {
            if ($operation instanceof Post && '/projects' === $operation->getUriTemplate()) {
                $createOperation = $operation;
                break;
            }
        }

        self::assertNotNull($createOperation, 'Could not find the POST /projects operation among ProjectRequest\'s operations.');

        $openapi = $createOperation->getOpenapi();
        self::assertInstanceOf(Operation::class, $openapi, 'POST /projects must declare its OpenAPI operation.');

        $requestBody = $openapi->getRequestBody();
        self::assertInstanceOf(RequestBody::class, $requestBody);

        $content = $requestBody->getContent();
        self::assertNotNull($content);
        self::assertArrayHasKey('multipart/form-data', $content);

        $mediaType = $content['multipart/form-data'];
        self::assertInstanceOf(MediaType::class, $mediaType);

        $schema = $mediaType->getSchema();
        self::assertInstanceOf(\ArrayObject::class, $schema);

        $properties = $schema['properties'];
        self::assertIsArray($properties);

        $imageProperty = $properties['image'];
        self::assertIsArray($imageProperty);

        $description = $imageProperty['description'];
        self::assertIsString($description);

        self::assertStringNotContainsString(
            'MiB',
            $description,
            'The documented ceiling must not claim a flat binary-MiB size; the effective limit is configurable.',
        );
        self::assertStringContainsString(
            'configured free-tier maximum',
            $description,
            'The documentation must say the effective limit is the configured free-tier maximum.',
        );
        self::assertStringContainsString(
            '30 MB',
            $description,
            'The documentation must still name the 30 MB hard ceiling.',
        );
        self::assertSame(
            'JPEG, PNG, WebP or GIF. The effective limit is the configured free-tier maximum (10 MB by default); 30 MB is the hard ceiling.',
            $description,
        );
    }
}
