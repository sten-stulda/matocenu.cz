<?php

namespace App\Domain\Analytics\Contracts;

interface DashboardWidget
{
    public function permission(): string;

    public function view(): string;

    /** @return array<string, mixed> */
    public function data(): array;
}
