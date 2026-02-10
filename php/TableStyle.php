<?php
declare(strict_types=1);

interface TableStyle
{
    public function table(): string;
    public function thead(): string;
    public function headlineCell(): string;
    public function filterButton(): string;
    public function icon(string $name): string;
    public function filterInput(): string;
}
