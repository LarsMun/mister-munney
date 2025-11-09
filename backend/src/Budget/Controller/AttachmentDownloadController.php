<?php

namespace App\Budget\Controller;

use App\Budget\Repository\BudgetRepository;
use App\Budget\Service\AttachmentStorageService;
use App\Entity\ExternalPayment;
use App\Entity\ProjectAttachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class AttachmentDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BudgetRepository $budgetRepository,
        private readonly AttachmentStorageService $attachmentStorage,
        private readonly string $projectDir
    ) {
    }

    /**
     * Download an external payment attachment
     */
    #[Route('/external-payments/{paymentId}/download', name: 'download_external_payment_attachment', methods: ['GET'])]
    public function downloadExternalPaymentAttachment(int $paymentId, Request $request): Response
    {
        // Get payment
        $payment = $this->entityManager->getRepository(ExternalPayment::class)->find($paymentId);

        if (!$payment) {
            throw new NotFoundHttpException('Payment not found');
        }

        // Verify ownership
        $user = $this->getUser();
        if ($user) {
            $budget = $payment->getBudget();
            if ($budget && $budget->getAccount() && !$budget->getAccount()->isOwnedBy($user)) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
        }

        // Get attachment URL
        $attachmentUrl = $payment->getAttachmentUrl();
        if (!$attachmentUrl) {
            throw new NotFoundHttpException('No attachment found');
        }

        return $this->serveFile($attachmentUrl, $request);
    }

    /**
     * Download a project attachment
     */
    #[Route('/project-attachments/{attachmentId}/download', name: 'download_project_attachment', methods: ['GET'])]
    public function downloadProjectAttachment(int $attachmentId, Request $request): Response
    {
        // Get attachment
        $attachment = $this->entityManager->getRepository(ProjectAttachment::class)->find($attachmentId);

        if (!$attachment) {
            throw new NotFoundHttpException('Attachment not found');
        }

        // Verify ownership
        $user = $this->getUser();
        if ($user) {
            $budget = $attachment->getBudget();
            if ($budget && $budget->getAccount() && !$budget->getAccount()->isOwnedBy($user)) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
        }

        // Get file URL
        $fileUrl = $attachment->getFileUrl();
        if (!$fileUrl) {
            throw new NotFoundHttpException('No file found');
        }

        return $this->serveFile($fileUrl, $request, $attachment->getOriginalFilename());
    }

    /**
     * Serve a file with proper headers
     */
    private function serveFile(string $url, Request $request, ?string $originalFilename = null): Response
    {
        // Convert URL to filesystem path
        $baseDir = $this->projectDir . '/public';
        $filePath = $baseDir . $url;

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('File not found');
        }

        // Determine Content-Type
        $mimeType = mime_content_type($filePath);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }

        // Create response
        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', $mimeType);

        // Determine disposition (inline for preview, attachment for download)
        $disposition = $request->query->get('download') === '1' ? 'attachment' : 'inline';

        // Use original filename if provided, otherwise extract from path
        $filename = $originalFilename ?? basename($filePath);

        $response->setContentDisposition(
            $disposition,
            $filename
        );

        return $response;
    }
}
