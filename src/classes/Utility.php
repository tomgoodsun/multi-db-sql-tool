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
}
