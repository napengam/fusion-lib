<?php
declare(strict_types=1);

final class BulmaTableStyle implements TableStyle
{
    public function table(): string
    {
        return 'table is-bordered is-striped is-narrow is-hoverable';
    }

    public function thead(): string
    {
        return 'has-background-primary-95';
    }

    public function headlineCell(): string
    {
        return 'has-text-centered';
    }

    public function filterButton(): string
    {
        return 'button is-small';
    }

    public function icon(string $name): string
    {
        return match ($name) {
            'filter' => '<i class="fa-xs fa-solid fa-filter"></i>',
            'print'  => '<i class="fa-xs fa-solid fa-print"></i>',
            default  => ''
        };
    }

    public function filterInput(): string
    {
        return 'input is-small';
    }
}
