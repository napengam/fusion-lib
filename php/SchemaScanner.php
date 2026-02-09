<?php

/**
 * SchemaScanner
 *
 * Scans a MySQL database using PDO and generates a cached schema configuration file.
 * Detects:
 *   - Primary keys
 *   - 1→N relations (foreign keys)
 *   - N→M relations (junction tables with ≥2 FKs)
 *
 * Output:
 *   schema_cache.php (short array syntax)
 */
class SchemaScanner
{
    private PDO $pdo;
    private string $database;
    private array $schema = [];

    public function __construct(PDO $pdo, string $database)
    {
        $this->pdo = $pdo;
        $this->database = $database;
    }

    /**
     * Run the full scan and generate the schema cache file.
     *
     * @param string $outputFile Path to save the schema file.
     * @return void
     * @throws Exception
     */
    public function generateCache(string $outputFile): void
    {
        $tables = $this->getTables();

        foreach ($tables as $table) {
            $primaryKeys = $this->getPrimaryKeys($table);
            $foreignKeys = $this->getForeignKeys($table);
            $relations = $this->detectRelations($table, $foreignKeys);

            $this->schema[$table] = [
                'primary_keys' => $primaryKeys,
                'relations' => $relations,
            ];
        }

        $this->writeCacheFile($outputFile);
        echo "✅ Schema cache saved to {$outputFile}\n";
    }

    /**
     * Get all table names in the database.
     */
    private function getTables(): array
    {
        $stmt = $this->pdo->query('SHOW TABLES');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all primary key columns for a given table.
     */
    private function getPrimaryKeys(string $table): array
    {
        $sql = '
            SELECT kcu.COLUMN_NAME
            FROM information_schema.TABLE_CONSTRAINTS tc
            JOIN information_schema.KEY_COLUMN_USAGE kcu 
                ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
            WHERE tc.CONSTRAINT_TYPE = \'PRIMARY KEY\'
              AND tc.TABLE_SCHEMA = DATABASE()
              AND tc.TABLE_NAME = :table
            ORDER BY kcu.ORDINAL_POSITION
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['table' => $table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all foreign keys where this table is either parent or child.
     */
    private function getForeignKeys(string $table): array
    {
        $sql = '
            SELECT 
                kcu.TABLE_NAME AS table_name,
                kcu.COLUMN_NAME AS column_name,
                kcu.REFERENCED_TABLE_NAME AS referenced_table,
                kcu.REFERENCED_COLUMN_NAME AS referenced_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
              AND (kcu.TABLE_NAME = :table OR kcu.REFERENCED_TABLE_NAME = :table)
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['table' => $table]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detect 1→N and N→M relations for the table.
     */
    private function detectRelations(string $table, array $fkRows): array
    {
        $relations = [
            '1N' => [],
            'NM' => [],
        ];

        foreach ($fkRows as $fk) {
            // This table is the master (referenced by others)
            if ($fk['referenced_table'] === $table) {
                $relations['1N'][$fk['table_name']][$fk['column_name']] = $fk['referenced_column'];
            }

            // This table is the child (references others)
            if ($fk['table_name'] === $table) {
                $relations['1N'][$fk['referenced_table']][$fk['column_name']] = $fk['referenced_column'];
            }
        }

        // Detect junction (N→M) tables: 2+ FKs in same table
        $linkFKs = array_filter($fkRows, static function ($r) use ($table) {
            return $r['table_name'] === $table;
        });

        if (count($linkFKs) >= 2) {
            foreach ($linkFKs as $fk) {
                $relations['NM'][$fk['referenced_table']][$fk['column_name']] = $fk['referenced_column'];
            }
        }

        return $relations;
    }

    /**
     * Write schema array to PHP file using short array syntax.
     */
    private function writeCacheFile(string $outputFile): void
    {
        $export = var_export($this->schema, true);

        // Convert array(...) to short syntax [...]
        $export = preg_replace([
            '/array\s*\(/',
            '/\)(,)?/',
        ], [
            '[',
            ']$1',
        ], $export);

        $phpCode = "<?php\nreturn {$export};\n";
        file_put_contents($outputFile, $phpCode);
    }
}
