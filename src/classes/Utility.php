<?php
namespace MultiDbSqlTool;

class Utility
{
    /**
     * Split multiple SQL statements into individual statements
     *
     * @param string $sql
     * @return array
     */
    public static function splitSqlStatements($sql)
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            if ($char === "'" || $char === '"') {
                $inString = !$inString;
            }
            if ($char === ';' && !$inString) {
                $statements[] = trim($buffer);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }

    /**
     * Clean the SQL query by removing comments and extra whitespace.
     *
     * @param string $sql
     * @return string
     */
    public static function cleanSql($sql)
    {
        // Remove comments and unnecessary whitespace
        $lines = explode("\n", $sql);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $line = preg_replace('/--.*$/', '', $line); // Remove single-line comments
            $line = preg_replace('/\/\*.*?\*\//s', '', $line); // Remove multi-line comments
            if (trim($line) !== '') {
                $cleanedLines[] = trim($line);
            }
        }
        return implode("\n", $cleanedLines);
    }

    /**
     * Check if the SQL query is read-only (SELECT, SHOW, DESCRIBE, EXPLAIN)
     *
     * @param string $sql
     * @return boolean
     */
    public static function isReadOnlyQuery($sql)
    {
        $sql = self::cleanSql($sql);
        $sql = trim($sql);
        $sql = preg_replace('/^[\s\(]+/', ' ', $sql); // Remove leading whitespace and parentheses
        $sql = strtoupper($sql);
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i';
        return preg_match($pattern, $sql) === 1;
    }

    /**
     * Check if the SQL query can be executed based on read-only mode setting.
     *
     * @param string $sql
     * @return boolean
     */
    public static function canExecuteQuery($sql)
    {
        if (!Config::getInstance()->isReadOnlyMode()) {
            return true;
        }

        $sqls = self::splitSqlStatements($sql);
        foreach ($sqls as $stmt) {
            if (!self::isReadOnlyQuery($stmt)) {
                return false;
            }
        }
        return true;
    }
}
