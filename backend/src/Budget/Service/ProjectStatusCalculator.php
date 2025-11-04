<?php

namespace App\Budget\Service;

use App\Entity\Budget;
use App\Entity\ExternalPayment;
use App\Entity\Transaction;
use App\Enum\ProjectStatus;
use Doctrine\ORM\EntityManagerInterface;

class ProjectStatusCalculator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calculate the project status based on transaction dates and duration
     *
     * @param Budget $budget The project budget
     * @param \DateTimeImmutable|null $referenceDate The date to compare against (defaults to today)
     * @return ProjectStatus The calculated status
     */
    public function calculateStatus(Budget $budget, ?\DateTimeImmutable $referenceDate = null): ProjectStatus
    {
        if (!$budget->isProject()) {
            throw new \InvalidArgumentException('Status calculation only applies to PROJECT type budgets');
        }

        $referenceDate = $referenceDate ?? new \DateTimeImmutable();

        // Collect all payment dates
        $allDates = $this->collectAllPaymentDates($budget);

        // If no payments yet, project is still ACTIVE (preparation phase)
        if (empty($allDates)) {
            return ProjectStatus::ACTIVE;
        }

        // Find the most recent payment date
        $lastPaymentDate = max($allDates);

        // Calculate months difference
        $monthsDiff = $this->calculateMonthsDifference($lastPaymentDate, $referenceDate);

        // Determine status based on duration setting
        if ($monthsDiff <= $budget->getDurationMonths()) {
            return ProjectStatus::ACTIVE;
        } else {
            return ProjectStatus::COMPLETED;
        }
    }

    /**
     * Collect all payment dates from transactions and external payments
     *
     * @param Budget $budget
     * @return array<\DateTimeImmutable>
     */
    private function collectAllPaymentDates(Budget $budget): array
    {
        $dates = [];

        // Get all transactions from categories linked to this budget
        $categories = $budget->getCategories();
        foreach ($categories as $category) {
            $transactions = $this->entityManager
                ->getRepository(Transaction::class)
                ->findBy(['category' => $category]);

            foreach ($transactions as $transaction) {
                $date = $transaction->getDate();
                $dates[] = $date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromMutable($date);
            }
        }

        // Get all external payments for this budget
        $externalPayments = $this->entityManager
            ->getRepository(ExternalPayment::class)
            ->findBy(['budget' => $budget]);

        foreach ($externalPayments as $payment) {
            $date = $payment->getPaidOn();
            $dates[] = $date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromMutable($date);
        }

        return $dates;
    }

    /**
     * Calculate the difference in months between two dates
     *
     * @param \DateTimeImmutable $startDate
     * @param \DateTimeImmutable $endDate
     * @return int Number of months between the dates (rounded down)
     */
    private function calculateMonthsDifference(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        $diff = $startDate->diff($endDate);

        // Convert to total months
        $months = ($diff->y * 12) + $diff->m;

        return $months;
    }

    /**
     * Get the last payment date for a project
     *
     * @param Budget $budget
     * @return \DateTimeImmutable|null
     */
    public function getLastPaymentDate(Budget $budget): ?\DateTimeImmutable
    {
        $allDates = $this->collectAllPaymentDates($budget);

        if (empty($allDates)) {
            return null;
        }

        return max($allDates);
    }
}
