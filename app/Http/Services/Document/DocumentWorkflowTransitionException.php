<?php

namespace App\Http\Services\Document;

use Exception;

class DocumentWorkflowTransitionException extends Exception
{
    /**
     * @param array<int, string> $allowedStatuses
     */
    public function __construct(
        public readonly string $action,
        public readonly string $currentStatus,
        public readonly array $allowedStatuses,
    ) {
        parent::__construct("Invalid status transition for {$action}");
    }
}
