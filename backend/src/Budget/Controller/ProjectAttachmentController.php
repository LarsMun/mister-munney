<?php

namespace App\Budget\Controller;

use App\Budget\Repository\BudgetRepository;
use App\Budget\Service\AttachmentStorageService;
use App\Entity\ProjectAttachment;
use App\FeatureFlag\Service\FeatureFlagService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ProjectAttachmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BudgetRepository $budgetRepository,
        private readonly AttachmentStorageService $attachmentStorage,
        private readonly FeatureFlagService $featureFlagService
    ) {
    }

    /**
     * Upload and create a project attachment
     */
    #[Route('/budgets/{budgetId}/attachments', name: 'create_project_attachment', methods: ['POST'])]
    public function createProjectAttachment(int $budgetId, Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Get project budget
        $budget = $this->budgetRepository->find($budgetId);

        if (!$budget) {
            throw new NotFoundHttpException('Budget not found');
        }

        if (!$budget->isProject()) {
            throw new \InvalidArgumentException('Attachments can only be added to project budgets');
        }

        // Get uploaded file
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        // Get form data
        $title = $request->request->get('title');
        $description = $request->request->get('description');
        $category = $request->request->get('category');

        if (!$title) {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Store file
            $url = $this->attachmentStorage->storeProjectAttachment($file);

            // Create attachment entity
            $attachment = new ProjectAttachment();
            $attachment->setBudget($budget);
            $attachment->setTitle($title);
            $attachment->setDescription($description);
            $attachment->setFileUrl($url);
            $attachment->setOriginalFilename($file->getClientOriginalName());
            $attachment->setCategory($category);

            $this->entityManager->persist($attachment);
            $this->entityManager->flush();

            return $this->json([
                'id' => $attachment->getId(),
                'title' => $attachment->getTitle(),
                'description' => $attachment->getDescription(),
                'fileUrl' => $attachment->getFileUrl(),
                'originalFilename' => $attachment->getOriginalFilename(),
                'category' => $attachment->getCategory(),
                'uploadedAt' => $attachment->getUploadedAt()->format('Y-m-d H:i:s'),
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all project attachments for a budget
     */
    #[Route('/budgets/{budgetId}/attachments', name: 'get_project_attachments', methods: ['GET'])]
    public function getProjectAttachments(int $budgetId): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Get project budget
        $budget = $this->budgetRepository->find($budgetId);

        if (!$budget) {
            throw new NotFoundHttpException('Budget not found');
        }

        if (!$budget->isProject()) {
            throw new \InvalidArgumentException('Budget is not a project');
        }

        // Get attachments
        $attachments = $this->entityManager
            ->getRepository(ProjectAttachment::class)
            ->findBy(['budget' => $budget], ['uploadedAt' => 'DESC']);

        // Map to DTOs
        $dtos = array_map(function (ProjectAttachment $attachment) {
            return [
                'id' => $attachment->getId(),
                'title' => $attachment->getTitle(),
                'description' => $attachment->getDescription(),
                'fileUrl' => $attachment->getFileUrl(),
                'originalFilename' => $attachment->getOriginalFilename(),
                'category' => $attachment->getCategory(),
                'uploadedAt' => $attachment->getUploadedAt()->format('Y-m-d H:i:s'),
            ];
        }, $attachments);

        return $this->json($dtos);
    }

    /**
     * Delete a project attachment
     */
    #[Route('/attachments/{id}', name: 'delete_project_attachment', methods: ['DELETE'])]
    public function deleteProjectAttachment(int $id): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Get attachment
        $attachment = $this->entityManager->getRepository(ProjectAttachment::class)->find($id);

        if (!$attachment) {
            throw new NotFoundHttpException('Attachment not found');
        }

        // Delete file
        if ($attachment->getFileUrl()) {
            $this->attachmentStorage->delete($attachment->getFileUrl());
        }

        // Remove attachment
        $this->entityManager->remove($attachment);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
