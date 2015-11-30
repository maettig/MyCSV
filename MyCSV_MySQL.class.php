<?php

require_once("MyCSV.class.php");

/**
 * MyCSV from and to MySQL import and export methods.
 *
 * @author Thiemo Mättig (http://maettig.com/)
 * @version 2003-12-28
 * @package TM
 */
class MyCSV_MySQL extends MyCSV
{
    /**
     * Imports a MyCSV table from a MySQL table.
     *
     * Any data already in the MyCSV object will be lost. Returns false on
     * failure.
     *
     * @param source mixed
     * @return bool
     */
    function fromMySQL($source = "")
    {
        // Assume source is a string containing the table name.
        if (! is_resource($source))
        {
            // Use the internal table name already set in the constructor, hopefully.
            if (! $source) list($source) = explode(".", basename($this->filename));
            $source = mysql_query("SELECT * FROM " . $source);
        }
        // Break if the source is still false.
        if (! $source) return false;
        $this->fields = array("id");
        $this->data = array();
        while ($row = mysql_fetch_assoc($source)) $this->insert($row);
    }

    /**
     * Exports a MyCSV table into a MySQL table.
     *
     * Any data already in the MySQL table will be lost.
     *
     * @param tablename string
     * @return bool
     */
    function toMySQL($tablename = "")
    {
        $sqlArray = $this->toSQL($tablename);
        while ($sql = each($sqlArray)) if (! mysql_query($sql)) return false;
    }

    /**
     * Prints a SQL dump of the table.
     *
     * @param tablename string
     * @return bool
     */
    function dumpSQL($tablename = "")
    {
        echo implode("\n", $this->toSQL($tablename));
    }

    /**
     * Creates an array of SQL statements.
     *
     * @param tablename string
     * @return array
     */
    function toSQL($tablename = "")
    {
        if (! $tablename)
        {
            // Use the CSV filename as the tablename if required.
            $tablename = preg_replace('/\.[^.]*$/', '', basename($this->filename));
        }

        $types = array(
            0 => "TINYINT",  // -128 to 127
            1 => "SMALLINT", // -32768 to 32767
            2 => "INT",      // -2147483648 to 2147483647
            3 => "DOUBLE",
            4 => "VARCHAR(255)",
            5 => "LONGTEXT",
            6 => "LONGBLOB");
        foreach ($this->fields as $field) $t[$field] = 0;
        reset($this->data);
        while ($row = $this->each())
        {
            foreach ($row as $field => $value)
            {
                if (preg_match('/[\x00-\x06\x08\x0B-\x0C\x0E-\x13\x16-\x1F]/', $value))
                    $t[$field] = 6;
                elseif (strlen($value) > 255 || strpos($value, "\n") !== false)
                    $t[$field] = max($t[$field], 5);
                elseif (! empty($value) && ! is_numeric($value))
                    $t[$field] = max($t[$field], 4);
                elseif (! preg_match('/^[+-]?\d{0,9}$/s', $value))
                    $t[$field] = max($t[$field], 3);
                elseif (! preg_match('/^[+-]?\d{0,4}$/s', $value))
                    $t[$field] = max($t[$field], 2);
                elseif (! preg_match('/^[+-]?\d{0,2}$/s', $value))
                    $t[$field] = max($t[$field], 1);
            }
        }

        // Drop the MySQL table if exists.
        $sqlArray = array("DROP TABLE IF EXISTS " . $this->_backquote($tablename) . ";");
        $sql = "CREATE TABLE " . $this->_backquote($tablename) . " (\n";
        foreach ($this->fields as $field)
        {
            $sql .= "  " . $this->_backquote($field) . " ";
            $sql .= $types[$t[$field]] . " NOT NULL";
            if (strtolower($field) == "id") $sql .= " AUTO_INCREMENT";
            $sql .= ",\n";
        }
        $sql .= "  PRIMARY KEY (" . $this->_backquote("id") . ")\n);";
        $sqlArray[] = $sql;

        reset($this->data);
        while ($row = $this->each())
        {
            foreach ($row as $field => $value)
            {
                $rowSql[$this->_backquote($field)] = "'" . mysql_escape_string($value) . "'";
            }
            $sql = "INSERT INTO " . $this->_backquote($tablename) . " (";
            $sql .= implode(", ", array_keys($rowSql));
            $sql .= ") VALUES (" . implode(", ", $rowSql) . ");";
            $sqlArray[] = $sql;
        }

        return $sqlArray;
    }

    /**
     * Quotes database and table names with back quotes. Maximum length is 64
     * characters. Allowed characters are "any character that is allowed in a
     * file name", except "/", ".", ASCII(0), ASCII(255) and the quoting
     * character (from the MySQL manual).
     *
     * @param name string
     * @return string
     */
    function _backquote($name)
    {
        $name = strtr($name, chr(0) . "./`" . chr(255), " _-'y");
        return "`" . substr($name, 0, 64) . "`";
    }
}

?>