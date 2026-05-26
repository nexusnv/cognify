<?php

namespace Domains\Approval\Services;

use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ApprovalSubjectRegistry
{
    /** @var array<string, ApprovalSubjectHandler> */
    private array $bySubjectType = [];

    /** @var array<class-string<Model>, ApprovalSubjectHandler> */
    private array $byModelClass = [];

    /**
     * @param  iterable<int, ApprovalSubjectHandler>  $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->bySubjectType[$handler->subjectType()] = $handler;
            $this->byModelClass[$handler->modelClass()] = $handler;
        }
    }

    public function forSubject(Model $subject): ApprovalSubjectHandler
    {
        foreach ($this->byModelClass as $class => $handler) {
            if ($subject instanceof $class) {
                return $handler;
            }
        }

        throw new InvalidArgumentException('Unsupported approval subject model ['.$subject::class.'].');
    }

    public function forStoredSubject(string $subjectType): ApprovalSubjectHandler
    {
        $handler = $this->byModelClass[$subjectType] ?? $this->bySubjectType[$subjectType] ?? null;

        if (! $handler instanceof ApprovalSubjectHandler) {
            throw new InvalidArgumentException('Unsupported approval subject type ['.$subjectType.'].');
        }

        return $handler;
    }
}
