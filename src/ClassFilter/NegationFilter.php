<?php

declare(strict_types=1);

namespace Riverwaysoft\DtoConverter\ClassFilter;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;

class NegationFilter implements ClassFilterInterface
{
    public function __construct(private ClassFilterInterface $filter)
    {
    }

    public function isMatch(Class_|Enum_ $class): bool
    {
        return !$this->filter->isMatch($class);
    }
}
