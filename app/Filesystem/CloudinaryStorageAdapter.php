<?php

namespace App\Filesystem;

use Cloudinary\Cloudinary;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToDeleteFile;

class CloudinaryStorageAdapter implements FilesystemAdapter
{
    protected Cloudinary $cloudinary;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $config['cloud_name'] ?? env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => $config['api_key'] ?? env('CLOUDINARY_API_KEY'),
                'api_secret' => $config['api_secret'] ?? env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true,
            ]
        ]);
    }

    public function getUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        
        $publicId = pathinfo($path, PATHINFO_FILENAME);
        $folder = $this->config['folder'] ?? '';
        $fullPublicId = $folder ? $folder . '/' . $publicId : $publicId;

        // Ensure we request the file with its extension if needed, or rely on secureUrl
        return $this->cloudinary->image($fullPublicId)->toUrl();
    }

    public function fileExists(string $path): bool
    {
        try {
            $publicId = pathinfo($path, PATHINFO_FILENAME);
            $folder = $this->config['folder'] ?? '';
            $fullPublicId = $folder ? $folder . '/' . $publicId : $publicId;
            $this->cloudinary->adminApi()->resource($fullPublicId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $publicId = pathinfo($path, PATHINFO_FILENAME);
            $folder = $this->config['folder'] ?? '';
            $fullPublicId = $folder ? $folder . '/' . $publicId : $publicId;

            $tempFile = tempnam(sys_get_temp_dir(), 'cloudinary_');
            file_put_contents($tempFile, $contents);

            $this->cloudinary->uploadApi()->upload($tempFile, [
                'public_id' => $fullPublicId,
                'overwrite' => true,
                'resource_type' => 'auto',
            ]);

            @unlink($tempFile);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $resource, Config $config): void
    {
        try {
            $contents = stream_get_contents($resource);
            $this->write($path, $contents, $config);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        throw new \Exception('Read operation not supported.');
    }

    public function readStream(string $path)
    {
        throw new \Exception('Read stream operation not supported.');
    }

    public function delete(string $path): void
    {
        try {
            $publicId = pathinfo($path, PATHINFO_FILENAME);
            $folder = $this->config['folder'] ?? '';
            $fullPublicId = $folder ? $folder . '/' . $publicId : $publicId;
            $this->cloudinary->uploadApi()->destroy($fullPublicId);
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        // Not implemented
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Not needed
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Not needed
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, null, 'image/jpeg');
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, time());
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, 0);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // Not implemented
    }
}
