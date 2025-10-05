<?php

namespace App\Transaction\Mapper;

use App\Transaction\DTO\TransactionFilterDTO;
use Symfony\Component\HttpFoundation\Request;

class TransactionFilterMapper
{
    public function fromRequest(Request $request, int $accountId): TransactionFilterDTO
    {
        $dto = new TransactionFilterDTO();
        $dto->accountId = $accountId;
        $dto->search = $request->query->get('search');
        $dto->startDate = $request->query->get('startDate');
        $dto->endDate = $request->query->get('endDate');
        $min = $request->query->get('minAmount');
        $dto->minAmount = is_numeric($min) ? (float) $min : null;
        $max = $request->query->get('maxAmount');
        $dto->maxAmount = is_numeric($max) ? (float) $max : null;
        $dto->transactionType = $request->query->get('transactionType');
        $dto->sortBy = $request->query->get('sortBy') ?? 'date';
        $dto->sortDirection = $request->query->get('sortDirection') ?? 'DESC';

        return $dto;
    }
}