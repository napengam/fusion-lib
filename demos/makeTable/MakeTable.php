<?php
declare(strict_types=1);

final class MakeTable
{
    public string $title;
    public string $tableId = '';

    private array $out = [];
    private bool $hasHead = false;
    private int $numCols = 0;
    private string $footLine = '';
    private TableStyle $style;

    public function __construct(string $title, ?TableStyle $style = null)
    {
        $this->title = $title;
        $this->style = $style ?? new PlainTableStyle();
    }

    public function outRow(array $line, string $attributes = ''): void
    {
        if (!$this->hasHead) {
            $this->initTable($line);
            return;
        }

        $attr = $attributes !== '' ? " $attributes" : '';
        $this->out[] = "<tr{$attr}>";

        foreach ($line as $cell) {
            $this->out[] = $this->renderCell($cell, 'td');
        }

        $this->out[] = '</tr>';
    }

    public function setHeadLine(string $headline): void
    {
        $this->out[2] =
            "<tr><th colspan=\"{$this->numCols}\" class=\"{$this->style->headlineCell()}\">{$headline}</th></tr>";
    }

    public function setFootLine(string $footer): void
    {
        $this->footLine =
            "<tr><th colspan=\"{$this->numCols}\">{$footer}</th></tr>";
    }

    public function closeTable(): string
    {
        if ($this->footLine !== '') {
            $this->out[] = $this->footLine;
        }

        $this->out[] = '</tbody></table>';
        return implode('', $this->out);
    }

    // -------------------------------------------------
    // Internals
    // -------------------------------------------------

    private function initTable(array $line): void
    {
        $this->hasHead = true;
        $this->tableId = 'tt' . bin2hex(random_bytes(4));

        $colspan  = count($line);
        $filterId = "filter{$this->tableId}";

        $this->out[] =
            "<table id=\"{$this->tableId}\" class=\"{$this->style->table()}\">";

        $this->out[] =
            "<thead class=\"{$this->style->thead()}\">";

        // Title row
        $this->out[] = <<<HTML
<tr>
    <th colspan="{$colspan}" class="{$this->style->headlineCell()}">
        <span class="{$this->style->filterButton()}"
              onclick="filterTable.filterOnOff('{$filterId}')">
            {$this->style->icon('filter')}
        </span>
        {$this->title}
        <span class="{$this->style->filterButton()}"
              onclick="filterTable.filterOnOff('{$filterId}',1);window.print()">
            {$this->style->icon('print')}
        </span>
    </th>
</tr>
HTML;

        // Filter row
        $this->out[] = "<tr id=\"{$filterId}\" style=\"display:none\">";
        foreach ($line as $i => $_) {
            $this->out[] =
                "<th><input class=\"{$this->style->filterInput()}\" " .
                "onchange=\"filterTable.searchRows('{$filterId}')\" " .
                "id=\"idtitle{$i}{$this->tableId}\" type=\"text\"></th>";
        }
        $this->out[] = '</tr>';

        // Header row
        $this->out[] = '<tr>';
        foreach ($line as $cell) {
            $this->out[] = $this->renderCell($cell, 'th');
        }
        $this->out[] = '</tr>';

        $this->out[] = '</thead><tbody>';
        $this->numCols = $colspan;
    }

    private function renderCell(mixed $cell, string $tag): string
    {
        if (!is_array($cell)) {
            return "<{$tag}>" . htmlspecialchars((string)$cell) . "</{$tag}>";
        }

        $attr = $cell['atrib'] ?? $cell[0] ?? '';
        $val  = $cell['value'] ?? $cell[1] ?? '';

        return "<{$tag} {$attr}>{$val}</{$tag}>";
    }
}
