<?php

namespace App\Project\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class ProjectImageStorage
{
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire(service: 'project.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function store(UploadedFile $image): string
    {
        $extension = $image->guessExtension()
            ?? throw new \UnexpectedValueException('Could not determine the image extension.');

        $path = sprintf('%s/image.%s', (string) Uuid::v7(), $extension);
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

    public function storeBytes(string $mimeType, string $bytes): string
    {
        $path = sprintf('%s/image.%s', (string) Uuid::v7(), $this->extensionFor($mimeType));
        $this->storage->write($path, $bytes);

        return $path;
    }

    private function extensionFor(string $mimeType): string
    {
        return self::EXTENSIONS[$mimeType]
            ?? throw new \UnexpectedValueException(sprintf('Unsupported image mime type "%s".', $mimeType));
    }

    public function remove(string $path): void
    {
        $this->storage->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->storage->fileExists($path);
    }

    public function read(string $path): string
    {
        return $this->storage->read($path);
    }

    public function mimeType(string $path): string
    {
        return $this->storage->mimeType($path);
    }
}
