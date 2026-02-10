<?php

declare(strict_types=1);

final class PlainTableStyle implements TableStyle {

    public function table(): string {
        return 'fusion-table';
    }

    public function thead(): string {
      return 'fusion-thead';
    }

    public function headlineCell(): string {
        return 'fusion-table-title';
    }

    public function filterButton(): string {
        return 'fusion-btn';
    }

    public function icon(string $name): string {
        return match ($name) {
            'filter' => '<button>[filter]</button>',
            'print' => '<button>[print]</button>',
            default => ''
        };
    }

    public function filterInput(): string {
        return 'fusion-input';
    }
}
