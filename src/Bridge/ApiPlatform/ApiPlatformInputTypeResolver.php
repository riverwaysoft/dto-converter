<?php

declare(strict_types=1);

namespace Riverwaysoft\DtoConverter\Bridge\ApiPlatform;

use Riverwaysoft\DtoConverter\Dto\DtoList;
use Riverwaysoft\DtoConverter\Dto\DtoType;
use Riverwaysoft\DtoConverter\Dto\PhpType\PhpUnknownType;
use Riverwaysoft\DtoConverter\Language\UnknownTypeResolverInterface;
use Riverwaysoft\DtoConverter\Language\UnsupportedTypeException;

class ApiPlatformInputTypeResolver implements UnknownTypeResolverInterface
{
    public function __construct(
        /** @var array<string, string> */
        private array $classMap = [],
        // https://www.typescriptlang.org/docs/handbook/2/template-literal-types.html
        private bool $useTypeScriptTemplateLiteral = false,
    ) {
    }

    public function supports(PhpUnknownType $type, DtoType $dto, DtoList $dtoList): bool
    {
        return $this->isApiPlatformInput($dto) && $this->isPropertyTypeClass($type) && !$this->isInput($type);
    }

    public function resolve(PhpUnknownType $type, DtoType $dto, DtoList $dtoList): mixed
    {
        if ($this->isPropertyEnum($type)) {
            if (!$dtoList->hasDtoWithType($type->getName())) {
                throw UnsupportedTypeException::forType($type, $dto->getName());
            }

            return sprintf("{ value: %s }", $type->getName());
        }

        if ($this->isEmbeddable($type)) {
            if (empty($this->classMap[$type->getName()])) {
                throw new \InvalidArgumentException(sprintf(
                    "There is no TypeScript type for %s. Please add %s to ApiPlatformInputTypeResolver constructor arguments",
                    $type->getName(),
                    $type->getName(),
                ));
            }
        }

        if (!empty($this->classMap[$type->getName()])) {
            return $this->classMap[$type->getName()];
        }

        if ($this->useTypeScriptTemplateLiteral) {
            return '`/api/${string}`';
        }

        return 'string';
    }

    private function isApiPlatformInput(DtoType $dto): bool
    {
        return str_ends_with(haystack: $dto->getName(), needle: 'Input');
    }

    private function isEmbeddable(PhpUnknownType $type): bool
    {
        return str_ends_with(haystack: $type->getName(), needle: 'Embeddable');
    }

    private function isInput(PhpUnknownType $type): bool
    {
        return str_ends_with(haystack: $type->getName(), needle: 'Input');
    }

    private function isPropertyEnum(PhpUnknownType $type): bool
    {
        return str_ends_with(haystack: $type->getName(), needle: 'Enum');
    }

    private function isPropertyTypeClass(PhpUnknownType $type): bool
    {
        return preg_match('/^[A-Z]/', $type->getName()) === 1;
    }
}
