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
            $subjectType = $handler->subjectType();
            if (array_key_exists($subjectType, $this->bySubjectType)) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate approval subject type [%s] registered by [%s].',
                    $subjectType,
                    get_class($handler),
                ));
            }

            $modelClass = $handler->modelClass();
            if (array_key_exists($modelClass, $this->byModelClass)) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate approval subject model class [%s] registered by [%s].',
                    $modelClass,
                    get_class($handler),
                ));
            }

            $this->bySubjectType[$subjectType] = $handler;
            $this->byModelClass[$modelClass] = $handler;
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
