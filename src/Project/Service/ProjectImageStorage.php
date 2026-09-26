<?php

namespace App\Project\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

class ProjectImageStorage
{
    public const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire(service: 'project.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function store(UploadedFile $image, Uuid $ownerId): string
    {
        $extension = $image->guessExtension()
            ?? throw new \UnexpectedValueException('Could not determine the image extension.');

        $path = sprintf('%s/%s/image.%s', self::prefixFor($ownerId), Uuid::v7(), $extension);
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

    public function storeBytes(string $mimeType, string $bytes, Uuid $ownerId): string
    {
        $path = sprintf('%s/%s/image.%s', self::prefixFor($ownerId), Uuid::v7(), $this->extensionFor($mimeType));
        $this->storage->write($path, $bytes);

        return $path;
    }

    private function extensionFor(string $mimeType): string
    {
        return self::EXTENSIONS[$mimeType]
            ?? throw new \UnexpectedValueException(sprintf('Unsupported image mime type "%s".', $mimeType));
    }

    public static function prefixFor(Uuid $ownerId): string
    {
        return substr(hash('sha256', (string) $ownerId), 0, 32);
    }

    public function removeForOwner(Uuid $ownerId): void
    {
        $this->storage->deleteDirectory(self::prefixFor($ownerId));
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
