<?php

namespace App\Budget\Controller;

use App\Account\Repository\AccountRepository;
use App\Budget\DTO\CreateExternalPaymentDTO;
use App\Budget\Repository\BudgetRepository;
use App\Budget\Service\AttachmentStorageService;
use App\Entity\ExternalPayment;
use App\Enum\PayerSource;
use App\FeatureFlag\Service\FeatureFlagService;
use App\Money\MoneyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ExternalPaymentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BudgetRepository $budgetRepository,
        private readonly AttachmentStorageService $attachmentStorage,
        private readonly FeatureFlagService $featureFlagService,
        private readonly MoneyFactory $moneyFactory,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly AccountRepository $accountRepository
    ) {
    }

    /**
     * Verify that the authenticated user owns the budget (through budget's account)
     */
    private function verifyBudgetOwnership(int $budgetId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $budget = $this->budgetRepository->find($budgetId);
        if (!$budget) {
            return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        $account = $budget->getAccount();
        if (!$account) {
            return $this->json(['error' => 'Budget has no associated account'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Verify that the authenticated user owns the external payment (through its budget's account)
     */
    private function verifyExternalPaymentOwnership(int $paymentId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $payment = $this->entityManager->getRepository(ExternalPayment::class)->find($paymentId);
        if (!$payment) {
            return $this->json(['error' => 'External payment not found'], Response::HTTP_NOT_FOUND);
        }

        $budget = $payment->getBudget();
        if (!$budget) {
            return $this->json(['error' => 'Payment has no associated budget'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $account = $budget->getAccount();
        if (!$account) {
            return $this->json(['error' => 'Budget has no associated account'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Create an external payment for a project
     */
    #[Route('/budgets/{budgetId}/external-payments', name: 'create_external_payment', methods: ['POST'])]
    public function createExternalPayment(int $budgetId, Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('external_payments')) {
            throw new AccessDeniedHttpException('External payments feature is disabled');
        }

        // Verify budget ownership
        if ($error = $this->verifyBudgetOwnership($budgetId)) {
            return $error;
        }

        // Get project budget
        $budget = $this->budgetRepository->find($budgetId);

        if (!$budget) {
            throw new NotFoundHttpException('Budget not found');
        }

        if (!$budget->isProject()) {
            throw new \InvalidArgumentException('External payments can only be added to project budgets');
        }

        // Deserialize and validate
        $createDTO = $this->serializer->deserialize(
            $request->getContent(),
            CreateExternalPaymentDTO::class,
            'json'
        );

        $errors = $this->validator->validate($createDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Create external payment
        $externalPayment = new ExternalPayment();
        $externalPayment->setBudget($budget);
        $externalPayment->setAmount($this->moneyFactory->fromFloat($createDTO->amount));
        $externalPayment->setPaidOn(new \DateTimeImmutable($createDTO->paidOn));
        $externalPayment->setPayerSource(PayerSource::from($createDTO->payerSource));
        $externalPayment->setNote($createDTO->note);

        $this->entityManager->persist($externalPayment);
        $this->entityManager->flush();

        // Return created payment
        return $this->json([
            'id' => $externalPayment->getId(),
            'budgetId' => $budget->getId(),
            'amount' => $this->moneyFactory->toString($externalPayment->getAmount()),
            'paidOn' => $externalPayment->getPaidOn()->format('Y-m-d'),
            'payerSource' => $externalPayment->getPayerSource()->value,
            'note' => $externalPayment->getNote(),
            'attachmentUrl' => $externalPayment->getAttachmentUrl(),
            'createdAt' => $externalPayment->getCreatedAt()->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an external payment
     */
    #[Route('/external-payments/{id}', name: 'update_external_payment', methods: ['PATCH'])]
    public function updateExternalPayment(int $id, Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('external_payments')) {
            throw new AccessDeniedHttpException('External payments feature is disabled');
        }

        // Verify external payment ownership
        if ($error = $this->verifyExternalPaymentOwnership($id)) {
            return $error;
        }

        // Get external payment
        $externalPayment = $this->entityManager->getRepository(ExternalPayment::class)->find($id);

        if (!$externalPayment) {
            throw new NotFoundHttpException('External payment not found');
        }

        // Parse request body
        $data = json_decode($request->getContent(), true);

        // Update fields if provided
        if (isset($data['amount'])) {
            $externalPayment->setAmount($this->moneyFactory->fromFloat($data['amount']));
        }

        if (isset($data['paidOn'])) {
            $externalPayment->setPaidOn(new \DateTimeImmutable($data['paidOn']));
        }

        if (isset($data['payerSource'])) {
            $externalPayment->setPayerSource(PayerSource::from($data['payerSource']));
        }

        if (isset($data['note'])) {
            $externalPayment->setNote($data['note']);
        }

        $this->entityManager->flush();

        // Return updated payment
        return $this->json([
            'id' => $externalPayment->getId(),
            'budgetId' => $externalPayment->getBudget()->getId(),
            'amount' => $this->moneyFactory->toString($externalPayment->getAmount()),
            'paidOn' => $externalPayment->getPaidOn()->format('Y-m-d'),
            'payerSource' => $externalPayment->getPayerSource()->value,
            'note' => $externalPayment->getNote(),
            'attachmentUrl' => $externalPayment->getAttachmentUrl(),
            'updatedAt' => $externalPayment->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete an external payment
     */
    #[Route('/external-payments/{id}', name: 'delete_external_payment', methods: ['DELETE'])]
    public function deleteExternalPayment(int $id): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('external_payments')) {
            throw new AccessDeniedHttpException('External payments feature is disabled');
        }

        // Verify external payment ownership
        if ($error = $this->verifyExternalPaymentOwnership($id)) {
            return $error;
        }

        // Get external payment
        $externalPayment = $this->entityManager->getRepository(ExternalPayment::class)->find($id);

        if (!$externalPayment) {
            throw new NotFoundHttpException('External payment not found');
        }

        // Delete attachment if exists
        if ($externalPayment->getAttachmentUrl()) {
            $this->attachmentStorage->delete($externalPayment->getAttachmentUrl());
        }

        // Remove external payment
        $this->entityManager->remove($externalPayment);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Upload attachment for an external payment
     */
    #[Route('/external-payments/{id}/attachment', name: 'upload_external_payment_attachment', methods: ['POST'])]
    public function uploadAttachment(int $id, Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('external_payments')) {
            throw new AccessDeniedHttpException('External payments feature is disabled');
        }

        // Verify external payment ownership
        if ($error = $this->verifyExternalPaymentOwnership($id)) {
            return $error;
        }

        // Get external payment
        $externalPayment = $this->entityManager->getRepository(ExternalPayment::class)->find($id);

        if (!$externalPayment) {
            throw new NotFoundHttpException('External payment not found');
        }

        // Get uploaded file
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Validate and store file
            $url = $this->attachmentStorage->store($file, $id);

            // Delete old attachment if exists
            if ($externalPayment->getAttachmentUrl()) {
                $this->attachmentStorage->delete($externalPayment->getAttachmentUrl());
            }

            // Update external payment
            $externalPayment->setAttachmentUrl($url);
            $this->entityManager->flush();

            return $this->json([
                'attachmentUrl' => $url,
                'message' => 'Attachment uploaded successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove attachment from an external payment (without deleting the payment)
     */
    #[Route('/external-payments/{id}/attachment', name: 'remove_external_payment_attachment', methods: ['DELETE'])]
    public function removeAttachment(int $id): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('external_payments')) {
            throw new AccessDeniedHttpException('External payments feature is disabled');
        }

        // Verify external payment ownership
        if ($error = $this->verifyExternalPaymentOwnership($id)) {
            return $error;
        }

        // Get external payment
        $externalPayment = $this->entityManager->getRepository(ExternalPayment::class)->find($id);

        if (!$externalPayment) {
            throw new NotFoundHttpException('External payment not found');
        }

        // Delete attachment if exists
        if ($externalPayment->getAttachmentUrl()) {
            $this->attachmentStorage->delete($externalPayment->getAttachmentUrl());
            $externalPayment->setAttachmentUrl(null);
            $this->entityManager->flush();
        }

        return $this->json([
            'message' => 'Attachment removed successfully',
        ]);
    }
}
