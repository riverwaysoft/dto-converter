<?php

declare(strict_types=1);

namespace App\CodeProvider;

class InlineCodeProvider implements CodeProviderInterface
{
    /** @param string[] $listings */
    public function __construct(private array $listings)
    {
    }

    public function getListings(): iterable
    {
        yield from $this->listings;
    }
}