<?php

namespace App\Exceptions;

/**
 * Thrown when approve/lock is blocked by incomplete nurse commission snapshots.
 */
class NurseCommissionApprovalBlockedException extends UserFacingException
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        private readonly array $messages,
    ) {
        parent::__construct($messages[0] ?? __('messages.reports.nurse_commission_incomplete'));
    }

    /**
     * @return list<string>
     */
    public function blockingMessages(): array
    {
        return $this->messages;
    }
}
