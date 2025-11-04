<?php

namespace App\Budget\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class AttachmentStorageService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB in bytes
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    private string $uploadDirectory;

    public function __construct(
        private readonly SluggerInterface $slugger,
        string $projectDir
    ) {
        $this->uploadDirectory = $projectDir . '/public/uploads/external-payments';
    }

    /**
     * Store an uploaded file and return its URL
     *
     * @throws \InvalidArgumentException if validation fails
     */
    public function store(UploadedFile $file, int $externalPaymentId): string
    {
        return $this->storeFile($file, 'external-payments');
    }

    /**
     * Store a project attachment file and return its URL
     *
     * @throws \InvalidArgumentException if validation fails
     */
    public function storeProjectAttachment(UploadedFile $file): string
    {
        return $this->storeFile($file, 'project-attachments');
    }

    /**
     * Internal method to store files
     */
    private function storeFile(UploadedFile $file, string $subdirectory): string
    {
        // Validate file
        $this->validate($file);

        // Generate storage path: {subdirectory}/{year}/{month}/{uuid}.{ext}
        $now = new \DateTimeImmutable();
        $year = $now->format('Y');
        $month = $now->format('m');

        $baseDir = dirname($this->uploadDirectory);
        $targetDirectory = $baseDir . '/' . $subdirectory . '/' . $year . '/' . $month;

        // Create directory if it doesn't exist
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        // Generate unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        // Move the file
        try {
            $file->move($targetDirectory, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Failed to upload file: ' . $e->getMessage());
        }

        // Return relative URL (will be served by Symfony)
        return '/uploads/' . $subdirectory . '/' . $year . '/' . $month . '/' . $newFilename;
    }

    /**
     * Validate uploaded file
     *
     * @throws \InvalidArgumentException if validation fails
     */
    public function validate(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('File size exceeds maximum of %d MB', self::MAX_FILE_SIZE / 1024 / 1024)
            );
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('File type "%s" is not allowed. Allowed types: PDF, JPG, PNG', $mimeType)
            );
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                sprintf('File extension "%s" is not allowed. Allowed extensions: %s',
                    $extension,
                    implode(', ', self::ALLOWED_EXTENSIONS)
                )
            );
        }
    }

    /**
     * Delete a file by its URL
     */
    public function delete(string $url): void
    {
        // Convert URL to filesystem path
        // URL format: /uploads/{subdirectory}/{year}/{month}/{filename}
        // Path format: {projectDir}/public/uploads/{subdirectory}/{year}/{month}/{filename}

        $baseDir = dirname($this->uploadDirectory);

        // Remove /uploads/ prefix and get full path
        $relativePath = str_replace('/uploads/', '', $url);
        $fullPath = $baseDir . '/' . $relativePath;

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Check if a file exists
     */
    public function exists(string $url): bool
    {
        $baseDir = dirname($this->uploadDirectory);
        $relativePath = str_replace('/uploads/', '', $url);
        $fullPath = $baseDir . '/' . $relativePath;

        return file_exists($fullPath);
    }

    /**
     * Get the upload directory path
     */
    public function getUploadDirectory(): string
    {
        return $this->uploadDirectory;
    }
}
