<?php

namespace App\Domains\Sales\Application\Composition;

final class EstimateAttributes
{
    public static function fromInput(array $input, int|string|null $companyId, ?int $creatorId): array
    {
        return ProposalAttributes::fromInput($input, $companyId, $creatorId);
    }
}
