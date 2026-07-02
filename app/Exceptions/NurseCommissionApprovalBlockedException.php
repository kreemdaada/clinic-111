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
        parent::__construct($messages[0] ?? 'This report cannot be approved until all nurse commission entries are complete.');
    }

    /**
     * @return list<string>
     */
    public function blockingMessages(): array
    {
        return $this->messages;
    }
}
