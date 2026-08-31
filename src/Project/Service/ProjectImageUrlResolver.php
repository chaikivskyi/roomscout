<?php

namespace App\Project\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProjectImageUrlResolver
{
    public function __construct(
        #[Autowire(service: 'project.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function resolve(?string $imagePath): ?string
    {
        return null !== $imagePath ? $this->storage->publicUrl($imagePath) : null;
    }
}
