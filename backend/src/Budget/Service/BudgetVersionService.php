<?php

namespace App\Budget\Service;

use App\Budget\DTO\CreateBudgetVersionDTO;
use App\Budget\DTO\UpdateBudgetVersionDTO;
use App\Budget\Repository\BudgetRepository;
use App\Budget\Repository\BudgetVersionRepository;
use App\Entity\Budget;
use App\Entity\BudgetVersion;
use DateTime;
use InvalidArgumentException;
use Money\Money;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BudgetVersionService
{
    private BudgetVersionRepository $budgetVersionRepository;
    private BudgetRepository $budgetRepository;

    public function __construct(
        BudgetVersionRepository $budgetVersionRepository,
        BudgetRepository $budgetRepository
    ) {
        $this->budgetVersionRepository = $budgetVersionRepository;
        $this->budgetRepository = $budgetRepository;
    }

    public function createSimpleVersion(BudgetVersion $budgetVersion): BudgetVersion
    {
        // Validation
        if (!$budgetVersion->getBudget()) {
            throw new InvalidArgumentException('Geen geldig budget gevonden');
        }

        if (!$budgetVersion->getEffectiveFromMonth()) {
            throw new InvalidArgumentException('Geen startdatum opgegeven');
        }

        if (!$budgetVersion->getMonthlyAmount() || $budgetVersion->getMonthlyAmount()->isNegative()) {
            throw new InvalidArgumentException('Geen geldig bedrag opgegeven');
        }

        // Save via repository
        return $this->budgetVersionRepository->save($budgetVersion);
    }

    /**
     * @throws NotFoundHttpException
     * @throws InvalidArgumentException
     */
    public function createVersion(int $accountId, int $budgetId, CreateBudgetVersionDTO $dto): BudgetVersion
    {
        $budget = $this->getBudgetWithAccountCheck($accountId, $budgetId);

        // Validate dates
        $this->validateVersionDates($dto->effectiveFromMonth, $dto->effectiveUntilMonth);

        // Check for overlapping versions
        $this->autoCloseOverlappingVersions($budget, $dto->effectiveFromMonth, $dto->effectiveUntilMonth);

        // Create new version
        $version = new BudgetVersion();
        $version->setBudget($budget);
        $version->setMonthlyAmount(Money::EUR($dto->monthlyAmount * 100));
        $version->setEffectiveFromMonth($dto->effectiveFromMonth);
        $version->setEffectiveUntilMonth($dto->effectiveUntilMonth);
        $version->setChangeReason($dto->changeReason);

        // Save version
        return $this->budgetVersionRepository->save($version);
    }

    /**
     * @throws NotFoundHttpException
     * @throws InvalidArgumentException
     */
    public function updateVersion(int $accountId, int $budgetId, int $versionId, UpdateBudgetVersionDTO $dto): BudgetVersion
    {
        $budget = $this->getBudgetWithAccountCheck($accountId, $budgetId);

        // Find the version
        $version = $this->budgetVersionRepository->find($versionId);

        if (!$version || $version->getBudget()->getId() !== $budget->getId()) {
            throw new NotFoundHttpException("Budget version with ID {$versionId} not found for this budget");
        }

        // Update fields if provided
        if ($dto->monthlyAmount !== null) {
            $version->setMonthlyAmount(Money::EUR($dto->monthlyAmount * 100));
        }

        if ($dto->effectiveFromMonth !== null) {
            $version->setEffectiveFromMonth($dto->effectiveFromMonth);
        }

        if ($dto->effectiveUntilMonth !== null) {
            $version->setEffectiveUntilMonth($dto->effectiveUntilMonth);
        }

        if ($dto->changeReason !== null) {
            $version->setChangeReason($dto->changeReason);
        }

        // Validate dates after updates
        $this->validateVersionDates(
            $version->getEffectiveFromMonth(),
            $version->getEffectiveUntilMonth()
        );

        // Auto-close overlapping versions (excluding current version being updated)
        $this->autoCloseOverlappingVersions(
            $budget,
            $version->getEffectiveFromMonth(),
            $version->getEffectiveUntilMonth(),
            $versionId  // Exclude this version from auto-close
        );

        return $this->budgetVersionRepository->save($version);
    }

    /**
     * @throws NotFoundHttpException
     * @throws InvalidArgumentException
     */
    public function deleteVersion(int $accountId, int $budgetId, int $versionId): void
    {
        $budget = $this->getBudgetWithAccountCheck($accountId, $budgetId);
        $version = $this->findVersionById($accountId, $budgetId, $versionId);

        // Prevent deletion of last version
        if ($budget->getBudgetVersions()->count() <= 1) {
            throw new InvalidArgumentException('Cannot delete the last version of a budget');
        }

        $this->budgetVersionRepository->delete($version);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function findVersionById(int $accountId, int $budgetId, int $versionId): BudgetVersion
    {
        $budget = $this->getBudgetWithAccountCheck($accountId, $budgetId);

        $version = $this->budgetVersionRepository->find($versionId);

        if (!$version || $version->getBudget()->getId() !== $budget->getId()) {
            throw new NotFoundHttpException("Budget version with ID {$versionId} not found for this budget");
        }

        return $version;
    }

    private function getBudgetWithAccountCheck(int $accountId, int $budgetId): Budget
    {
        $budget = $this->budgetRepository->find($budgetId);

        if (!$budget) {
            throw new NotFoundHttpException("Budget with ID {$budgetId} not found");
        }

        if ($budget->getAccount()->getId() !== $accountId) {
            throw new NotFoundHttpException("Budget does not belong to account {$accountId}");
        }

        return $budget;
    }

    /**
     * Automatically close any open-ended versions that would overlap with the new version
     */
    private function autoCloseOverlappingVersions(
        Budget $budget,
        string $newFromMonth,
        ?string $newUntilMonth,
        ?int $excludeVersionId = null
    ): void {
        foreach ($budget->getBudgetVersions() as $existingVersion) {
            if ($excludeVersionId && $existingVersion->getId() === $excludeVersionId) {
                continue;
            }

            $existingFrom = $existingVersion->getEffectiveFromMonth();
            $existingUntil = $existingVersion->getEffectiveUntilMonth();

            // ALLEEN automatisch sluiten als:
            // 1. Bestaande versie heeft GEEN einddatum (open-ended)
            // 2. Nieuwe versie start NA bestaande versie

            $canAutoClose = (
                $existingUntil === null &&  // Bestaande versie is open-ended
                $newFromMonth > $existingFrom  // Nieuwe versie start NA bestaande versie
            );

            if ($canAutoClose) {
                $monthBeforeNew = $this->subtractMonth($newFromMonth);
                $existingVersion->setEffectiveUntilMonth($monthBeforeNew);
                $this->budgetVersionRepository->save($existingVersion);
            } else {
                // Voor alle andere gevallen: check overlap en blokkeer
                $overlap = $this->rangesOverlap(
                    $newFromMonth,
                    $newUntilMonth,
                    $existingFrom,
                    $existingUntil
                );

                if ($overlap) {
                    throw new InvalidArgumentException(
                        "Cannot automatically resolve overlap with existing version from {$existingFrom}" .
                        ($existingUntil ? " until {$existingUntil}" : "") .
                        ". Please adjust or delete the existing version first."
                    );
                }
            }
        }
    }

    /**
     * Subtract one month from YYYY-MM format
     */
    private function subtractMonth(string $monthYear): string
    {
        $date = new DateTime($monthYear . '-01');
        $date->modify('-1 month');
        return $date->format('Y-m');
    }

    /**
     * Validate version date ranges
     */
    private function validateVersionDates(?string $fromMonth, ?string $untilMonth): void
    {
        if ($untilMonth && $fromMonth >= $untilMonth) {
            throw new InvalidArgumentException('Effective from date must be before effective until date');
        }
    }

    /**
     * Check if two date ranges overlap
     */
    private function rangesOverlap(
        string $start1,
        ?string $end1,
        string $start2,
        ?string $end2
    ): bool {
        // If either range has no end, it extends indefinitely
        $end1 = $end1 ?? '9999-12';
        $end2 = $end2 ?? '9999-12';

        // Ranges overlap if: start1 <= end2 AND start2 <= end1
        return $start1 <= $end2 && $start2 <= $end1;
    }

}