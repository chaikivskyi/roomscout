<?php

namespace App\Project\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use UnexpectedValueException;

final class ProjectImageStorage
{
    public function __construct(
        #[Autowire(service: 'project.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function store(UploadedFile $image): string
    {
        $extension = $image->guessExtension()
            ?? throw new UnexpectedValueException('Could not determine the image extension.');

        $path = sprintf('%s/image.%s', Uuid::v7()->toRfc4122(), $extension);
        $stream = fopen($image->getPathname(), 'rb');

        try {
            $this->storage->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    public function remove(string $path): void
    {
        $this->storage->delete($path);
    }
}
