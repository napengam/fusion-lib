<?php

declare(strict_types=1);

class MakeTable {

    public string $title;
    public string $tableId = '';
    private array $out = [];
    private bool $hasHead = false;
    private int $numCols = 0;
    private string $footLine = '';

    public function __construct(string $title) {
        $this->title = $title;
    }

    /**
     * Output one row.
     * First call initializes the table header.
     */
    public function outRow(array $line, string $attributes = ''): void {
        if (!$this->hasHead) {
            $this->initTable($line);
            return;
        }

        $attr = $attributes ? " $attributes" : '';
        $this->out[] = "<tr class=\"fusion-row\"$attr>";

        foreach ($line as $cell) {
            $this->out[] = $this->renderCell($cell, 'td');
        }

        $this->out[] = '</tr>';
    }

    public function setHeadLine(string $headline): void {
        $this->out[2] = "<tr class=\"fusion-table-headline\">
            <th colspan=\"{$this->numCols}\">$headline</th>
        </tr>";
    }

    public function setFootLine(string $footer): void {
        $this->footLine = "<tr class=\"fusion-table-footer\">
            <th colspan=\"{$this->numCols}\">$footer</th>
        </tr>";
    }

    public function closeTable(): string {
        if ($this->footLine) {
            $this->out[] = $this->footLine;
        }

        $this->out[] = '</tbody></table>';
        return implode('', $this->out);
    }

    /* -------------------------------------------------
     * Internals
     * ------------------------------------------------- */

    private function initTable(array $line): void {
        $this->hasHead = true;
        $this->numCols = count($line);
        $this->tableId = 'tt-' . bin2hex(random_bytes(4));
        $filterId = "filter-{$this->tableId}";

        $this->out[] = <<<HTML
<table id="{$this->tableId}" class="fusion-table">
<thead class="fusion-table-head">
<tr class="fusion-table-title">
    <th colspan="{$this->numCols}">
        <button class="fusion-btn fusion-btn-icon fusion-filter-toggle"
                onclick="filterTable.filterOnOff('{$filterId}')"
                aria-label="Filter"></button>

        <span class="fusion-table-title-text">{$this->title}</span>

        <button class="fusion-btn fusion-btn-icon fusion-print"
                onclick="filterTable.filterOnOff('{$filterId}',1);window.print()"
                aria-label="Print"></button>
    </th>
</tr>

<tr id="{$filterId}" class="fusion-filter-row" style="display:none">
HTML;

        foreach ($line as $i => $cell) {
            $this->out[] = <<<HTML
<th>
    <input class="fusion-filter-input"
           type="text"
           placeholder="filter…"
           onchange="filterTable.searchRows('{$filterId}')">
</th>
HTML;
        }

        $this->out[] = '</tr><tr class="fusion-header-row">';

        foreach ($line as $cell) {
            $this->out[] = $this->renderCell($cell, 'th');
        }

        $this->out[] = '</tr></thead><tbody class="fusion-table-body">';
    }

    private function renderCell(mixed $cell, string $tag): string {
        if (!is_array($cell)) {
            return "<$tag>" . htmlspecialchars((string) $cell) . "</$tag>";
        }

        $attr = $cell['atrib'] ?? $cell[0] ?? '';
        $val = $cell['value'] ?? $cell[1] ?? '';

        return "<$tag $attr>$val</$tag>";
    }
}
