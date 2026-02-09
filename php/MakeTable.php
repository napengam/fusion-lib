<?php

class MakeTable {

    public string $title;
    public string $tableId = '';
    public array $out = [];
    private bool $hasHead = false;
    private int $numCols = 0;
    private string $footLine = '';

    public function __construct(string $title) {
        $this->title = $title;
    }

    public function outRow(array $line, string $attributes = ''): void {
        if (!$this->hasHead) {
            $this->initTable($line);
            return;
        }

        $attr = $attributes ? " $attributes" : '';
        $this->out[] = "<tr $attr>";

        foreach ($line as $cell) {
            $this->out[] = $this->renderCell($cell, 'td');
        }

        $this->out[] = '</tr>';
    }

    public function setHeadLine(string $headline): void {
        $this->out[2] = "<tr><th colspan='{$this->numCols}'>$headline</th></tr>";
    }

    public function setFootLine(string $footer): void {
        $this->footLine = "<tr><th colspan='{$this->numCols}'>$footer</th></tr>";
    }

    public function closeTable(): string {
        if ($this->footLine) {
            $this->out[] = $this->footLine;
        }

        $this->out[] = "</tbody></table>";

        return implode('', $this->out);
    }

    // -------------------------
    // Helpers
    // -------------------------

    private function initTable(array $line): void {
        $this->hasHead = true;
        $this->tableId = 'tt' . bin2hex(random_bytes(4));
        $colspan = count($line);
        $filterId = "filter{$this->tableId}";

        $this->out[] = <<<HTML
            <table id="{$this->tableId}" class="table is-bordered is-striped is-narrow is-hoverable">
            <thead class="has-background-primary-95">
                <!-- Headline placeholder -->
                <tr>
                    <th colspan="{$colspan}" style="text-align:center">
                        <span class="button" onclick="filterTable.filterOnOff('{$filterId}')">
                            <i id="n{$filterId}" class="fa-xs fa-solid fa-filter"></i>
                        </span>
                        {$this->title}
                        <span style="margin-left:20px" class="button" onclick="filterTable.filterOnOff('{$filterId}',1);window.print()">
                            <i class="fa-xs fa-solid fa-print"></i>
                        </span>
                      
                    </th>
                </tr>
                <tr id="{$filterId}" style="display:none">
        HTML;

        // Filter row
        $placeholder = 'placeholder="Stichwort filtern"';
        foreach ($line as $i => $cell) {
            $this->out[] = <<<HTML
                <th>
                    <input onchange="filterTable.searchRows('{$filterId}')" $placeholder id="idtitle{$i}{$this->tableId}" name="title{$i}{$this->tableId}" type="text">
                </th>
            HTML;
        }

        $this->out[] = '</tr><tr>';

        // Header row
        foreach ($line as $cell) {
            $this->out[] = $this->renderCell($cell, 'th');
        }

        $this->out[] = '</tr></thead><tbody>';
        $this->numCols = $colspan;
    }

    private function renderCell($cell, string $tag): string {
        // Handle nulls or empty strings
        if (!is_array($cell)) {
            return "<$tag>" . htmlspecialchars((string) $cell) . "</$tag>";
        }

        // Support both associative and indexed arrays for attributes
        $attr = $cell['atrib'] ?? $cell[0] ?? '';
        $val = $cell['value'] ?? $cell[1] ?? '';

        return "<$tag $attr>$val</$tag>";
    }
}
