<?php

/*
 * LICENSE
 * 1. If you like to use this class for personal purposes, it's free.
 * 2. For comercial purposes, please contact me (http://maettig.com/email).
 *    I'll send a license to you.
 * 3. When you copy the framework you must copy this notice with the source
 *    code. You may alter the source code, but you have to put the original
 *    with your altered version.
 * 4. The license is for all files included in this bundle.
 *
 * KNOWN BUGS/LIMITATIONS/TODO
 * - seek(-1, SEEK_CUR) does not work!
 * - sort("... id") doesn't work properly in all cases!
 * - fetch_row/fetch_array() aren't supported, use fetch_assoc/each() instead.
 * - num_fields() etc. aren't supported, use count($table->fields) instead.
 * - create/create_table() is not supported, use add_field() instead.
 * - What about some kind of GROUP BY?
 * - Add where($filter = "field='n' OR strtolower(substr(r,0,1))='x'") becomes
 *   eval("$data['field']=='n' OR strtolower(substr($data['r'],0,1))=='x'");
 */

/**
 * For compatibility with older PHP versions before 4.4.0.
 */
if (!defined('SORT_LOCALE_STRING'))
    define('SORT_LOCALE_STRING', 5);

/**
 * More special sorting type flags for use in MyCSV::sort().
 */
if (!defined('SORT_NAT'))
    define('SORT_NAT', 16);
if (!defined('SORT_TIME'))
    define('SORT_TIME', 17);
if (!defined('SORT_NULL'))
    define('SORT_NULL', 32);

/**
 * A text file based database complement.
 *
 * This class handles standard CSV or TXT text files as they where database
 * tables. It supports most benefits of both SQL tables and PHP arrays. It
 * doesn't need a real database management system nor does it require any
 * knowlege of the SQL language. It hides all filesystem functions so you don't
 * have to deal with file pointers, field delimiters, escape sequences and so
 * on. Because it uses the widespreaded standard CSV file format you are able
 * to create, read and update the tables using any spreadsheet software (e.g.
 * Excel). It supports user defined table sort similar to ORDER BY, auto
 * incremented ID numbers, limitation and joins similar to LIMIT and LEFT OUTER
 * JOIN, it's binary safe (uses work arounds for all known fgetcsv() related
 * bugs) and lots more.
 *
 * File format restrictions by design ("it's not a bug, it's a feature"):
 * - The first line of the CSV file <b>must</b> contain the column names.
 * - The CSV file <b>should</b> contain a column named "id". If this column is
 *   missing, it is added automatically. See {@link fields()}.
 * - Some critical characters (NUL, double quotes, backslashes) are replaced or
 *   backslashed to make the resulting CSV file compatible to all PHP versions
 *   (all known versions do have one or more bugs in <code>fgetcsv()</code>).
 *   See {@link write()}.
 *
 * See {@link MyCSV()}, {@link dump()}, {@link limit()} or {@link join()} for
 * some examples.
 *
 * Don't hesitate to report bugs or feature requests.
 *
 * @author Thiemo Mättig (http://maettig.com/)
 * @version 2009-09-02
 * @package TM
 * @requires PHP 4.0.5 (array_search, strcoll)
 */
class MyCSV
{
    /**
     * Array containing all the table field names. First have to be "id".
     *
     * @var array
     * @see add_field(), insert()
     */
    var $fields = array("id");

    /**
     * Two dimensional associative array containing all the table row data.
     *
     * @var array
     * @see data(), each()
     */
    var $data = array();

    /**
     * The field delimiter for separating values in the CSV file. Default is ","
     * (default CSV style). If not, the class tries to use ";" (European/German
     * CSV style), "\t" (tabulator separated values), "\0", "|", "&" (URI
     * encoded/parameter style), ":" (Unix /etc/passwd style) and " " (log file
     * style). Normaly you don't have to touch this variable. Simply choose your
     * delimiter when creating your initial CSV file.
     *
     * @var string Field delimiter.
     */
    var $delimiter = ",";

    /**
     * @var int Last insert ID.
     * @access private
     * @see insert_id()
     */
    var $insert_id = null;

    /**
     * File name of the CSV table with or without the .csv file name extension.
     * Don't change this cause write() will not realize you want another file.
     *
     * @var string File name of the .csv file.
     * @access private
     * @see MyCSV(), read(), tablename(), write()
     */
    var $filename = "";

    /**
     * @var bool Resource handle to the CSV file already opened.
     * @access private
     */
    var $_fp = false;

    /**
     * @var int Number of rows to be fetched, set by limit().
     * @access private
     * @see limit()
     */
    var $_limitRows = null;

    /**
     * Reads a CSV file and returns it as a MyCSV object.
     *
     * Reads a table into a new MyCSV object. The file name may be entered with
     * or without the <code>.csv</code> file extension. If the file does not
     * exist it will be created when calling {@link write()}. Set <i>length</i>
     * to the maximum number of bytes per row you expect (as you did in
     * fgetcsv()). Default is 10000 bytes per line. Setting this to 1000 may
     * speed up the method if you'r sure there is no longer line.
     *
     * For example, create a file called <code>table.csv</code> with the
     * following content and call the script below.
     *
     * <pre>id,value
     * 3,Example
     * 4,Another value
     * 7,Blue</pre>
     *
     * <pre><?php
     * require_once("MyCSV.class.php");
     * $table = new MyCSV("table");
     * while ($row = $table->each()) {
     *     echo $row['id'] . " is " . $row['value'] . "<br>";
     * }
     * ?></pre>
     *
     * @param tablename string
     * @param length int
     * @return MyCSV
     */
    function MyCSV($tablename = "", $length = 10000)
    {
        // Warning: Constructors can not return anything.
        if ($tablename) $this->read($tablename, $length);
    }

    /**
     * @param tablename string
     * @param length int
     * @return bool
     * @access private
     */
    function read($tablename, $length = 10000)
    {
        $this->filename = $tablename;
        // Add default file extension if missing.
        if (!preg_match('/\.\w+$/', $this->filename)) $this->filename .= ".csv";

        // Break if the CSV file for this table does not exist.
        if (!strstr($this->filename, "://") && !file_exists($this->filename))
            return false;

        if (!empty($GLOBALS['_MyCSV_locked'][$this->filename]))
        {
            user_error(
                "MyCSV::read() failed, file $this->filename is open already",
                E_USER_WARNING);
            $this->filename = "";
            return false;
        }
        $GLOBALS['_MyCSV_locked'][$this->filename] = true;

        // Open the CSV file for exclusive reading and writing OR reading only.
        if (is_writable($this->filename))
        {
            $this->_fp = @fopen($this->filename, "r+b");
        }
        // is_writable() may fail if Windows locked the file.
        if (!$this->_fp) $this->_fp = fopen($this->filename, "rb");
        if (!$this->_fp) return false;
        if (!strstr($this->filename, "://")) flock($this->_fp, LOCK_EX);

        $this->fields = fgetcsv($this->_fp, $length, $this->delimiter);
        // Try some delimiters, but use the default if nothing was found.
        $delimiters = str_replace($this->delimiter, "", ",;\t\0|&: ") . $this->delimiter;
        while (count($this->fields) < 2)
        {
            $this->delimiter = $delimiters[0];
            if (!$delimiters = substr($delimiters, 1)) break;
            rewind($this->_fp);
            $this->fields = fgetcsv($this->_fp, $length, $this->delimiter);
        }
        // On what position is the ID field? Returns $i = -1 if not found.
        for ($i = count($this->fields) - 1; $i > -1; $i--)
        {
            if (strcasecmp($this->fields[$i], "id") == 0) break;
        }
        $lastId = 0;
        $fieldsCount = count($this->fields);
        while ($row = fgetcsv($this->_fp, $length, $this->delimiter))
        {
            // Add missing id numbers.
            $id = isset($row[$i]) ? $row[$i] : $lastId + 1;
            $lastId = max($id, $lastId);
            $count = min($fieldsCount, count($row));
            for ($c = 0; $c < $count; ++$c)
            {
                // Strip "smart" backslashes. This makes the CSV files
                // binary-safe and compatible to PHP >=4.3.2 (which is when Ilia
                // Alshanetsky started ruining fgetcsv).
                $row[$c] = strtr($row[$c], array("\\\x7F" => "\x00",
                                                 "\\\x93" => '"',
                                                 '\\\\'   => '\\'));

                $this->data[$id][$this->fields[$c]] = $row[$c];
            }
        }
        // Always move the id column to the front.
        unset($this->fields[$i]);
        array_unshift($this->fields, "id");

        return true;
    }

    /**
     * Adds a new field (column) to the table. Returns false on failure, e.g.
     * if the field already exists.
     *
     * @param field string
     * @param afterField string
     * @return bool
     * @see insert(), drop_field()
     */
    function add_field($field, $afterField = null)
    {
        // Break if the field name contains invalid characters or already exists.
        if (!preg_match('/^[\w\x7F-\xFF]+$/is', $field) || in_array($field, $this->fields))
        {
            return false;
        }
        if (isset($afterField) && in_array($afterField, $this->fields))
        {
            $newFields = array();
            foreach ($this->fields as $oldField)
            {
                $newFields[] = $oldField;
                if (strcasecmp($oldField, $afterField) == 0) $newFields[] = $field;
            }
            $this->fields = $newFields;
        }
        else $this->fields[] = $field;
        return true;
    }

    /**
     * Moves the internal row pointer to the specified row number. This is an
     * alias for <code>{@link seek}(<i>row_number</i>, SEEK_SET)</code>.
     *
     * @param row_number int
     * @return bool
     */
    function data_seek($row_number)
    {
        return $this->seek($row_number, SEEK_SET);
    }

    /**
     * Deletes a table row specified by the <i>id</i>. Deletes all rows if no
     * <i>id</i> is given.
     *
     * @param id mixed
     * @return void
     */
    function delete($id = null)
    {
        // If delete(array('id' => 3)) is called, delete row 3.
        if (is_array($id) && isset($id['id'])) $id = $id['id'];
        if (isset($id))
        {
            // Delete one row if a valid id is given.
            if (!is_array($id)) unset($this->data[$id]);
        }
        else
        {
            // Delete all rows if no id was given (or id is null).
            $this->data = array();
            // Do not reset the ID numbers cause they where used already.
            ++$this->insert_id;
        }
    }

    /**
     * Deletes a field/column from the table.
     *
     * Returns false on failure, e.g. if <i>field</i> does not exists. Rewinds
     * the internal array pointer to the first element on success.
     *
     * @param field string
     * @return bool
     */
    function drop_field($field)
    {
        if (is_array($field) || strcasecmp($field, "id") == 0) return false;
        $offset = array_search($field, $this->fields);
        if ($offset === false || $offset === null) return false;
        array_splice($this->fields, $offset, 1);
        while (list($id) = each($this->data)) unset($this->data[$id][$field]);
        reset($this->data);
        return true;
    }

    /**
     * Clears the table. Remove all columns and all fields too.
     *
     * @return void
     */
    function drop_table()
    {
        $this->fields = array("id");
        $this->data = array();
        $this->insert_id = null;
    }

    /**
     * Gets the current data row and increase the internal pointer. This is an
     * alias for {@link each()}.
     *
     * @return array
     */
    function fetch_assoc()
    {
        return $this->each();
    }

    /**
     * Inserts a new table row using the next free auto incremented ID number.
     *
     * @param data array
     * @return void
     */
    function insert($data)
    {
        if (!is_array($data)) return false;

        // If data contains an unused id number, use it.
        if (isset($data['id']) && strlen($data['id']))
        {
            $this->insert_id = $data['id'];
        }
        // First auto increment id is always 1, but only for the initial row.
        elseif (!isset($this->insert_id) && empty($this->data))
        {
            $this->insert_id = 1;
        }
        // Don't use ++ because "x"++ returns "y" and that's not what we want.
        if (isset($this->data[$this->insert_id])) $this->insert_id += 1;
        if (!isset($this->insert_id) || isset($this->data[$this->insert_id]))
        {
            $this->insert_id = max(array_keys($this->data)) + 1;
        }

        $this->data[$this->insert_id] = $data;

        // Fetch missing field/column names from the first data row if needed.
        // This can be used instead of add_field().
        if (empty($this->fields) || count($this->fields) < 2)
        {
            unset($data['id']);
            $this->fields = array_merge(array("id"), array_keys($data));
        }
    }

    /**
     * Gets the ID generated from the previous insert() call.
     *
     * @return int
     */
    function insert_id()
    {
        return isset($this->insert_id) ? $this->insert_id : false;
    }

    /**
     * Performs a left outer join with another table.
     *
     * The tables are merged using a foreign key of the left table and the
     * primary key of the right table. This adds temporary columns to the left
     * table (temporary means, they aren't stored using {@link write()}). A
     * slightly complex example:
     *
     * <pre>echo "&lt;pre>";
     * $rightTable = new MyCSV();
     * $rightTable->insert(array('id' => 7, 'color' => "red"));
     * $rightTable->insert(array('id' => 8, 'color' => "yellow"));
     * $rightTable->dump();
     * echo "\n";
     * $leftTable = new MyCSV();
     * $leftTable->insert(array('thing' => "Table", 'color_id' => 7));
     * $leftTable->insert(array('thing' => "Chair", 'color_id' => 8));
     * $leftTable->insert(array('thing' => "Lamp", 'color_id' => 7));
     * $leftTable->dump();
     * echo "\n";
     * $leftTable->join($rightTable, "color_id");
     * while ($row = $leftTable->each()) {
     *     echo $row['thing'] . " is " . $row['color'] . "\n";
     * }</pre>
     *
     * @param rightTable array
     * @param foreignKey string
     * @return void
     */
    function join(&$rightTable, $foreignKey)
    {
        if (is_array($rightTable)) $rightData = $rightTable;
        else
        {
            $rightData = $rightTable->data;
            // If filename is empty, prefix is empty too and not used below.
            $prefix = preg_replace('/\.\w+$/', '', basename($rightTable->filename));
        }

        reset($this->data);
        while (list($id) = each($this->data))
        {
            if (strcasecmp($foreignKey, "id") == 0) $fid = $id;
            else $fid = $this->data[$id][$foreignKey];
            if (isset($rightData[$fid]))
            {
                // Right table is modified here and used as some kind of cache.
                if (!empty($prefix) && !isset($rightData[$fid][$prefix . ".id"]))
                {
                    foreach ($rightData[$fid] as $field => $value)
                    {
                        $rightData[$fid][$prefix . "." . $field] = &$rightData[$fid][$field];
                    }
                }
                // Duplicate keys are used from the left (original) table.
                $this->data[$id] += $rightData[$fid];
            }
        }

        // Reset the internal pointer.
        reset($this->data);
    }

    /**
     * Limits the number of rows to be fetched.
     *
     * Use <code>limit(2)</code> to fetch the first two rows only when calling
     * {@link each()} (or {@link fetch_assoc()}). Use <code>limit(2, $id)</code>
     * to fetch the next two rows, where <code>$id</code> is calculated using
     * <code>{@link first()}</code> for the first page and using <code>{@link
     * next}($id, 2)</code>, <code>next($id, 4)</code> and so on for all other
     * pages. Example:
     *
     * <pre>$table = new MyCSV("table");
     * for ($i = 10; $i < 21; $i++) {
     *   $table->insert(array('text' => "Text $i"));
     * }
     * // Order the table first because limit() depends on this.
     * $table->sort("text DESC");
     * // Limit to 5 rows starting from a specific id.
     * $rows = 5;
     * $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : $table->first();
     * $table->limit($rows, $id);
     * while ($row = $table->each()) {
     *   echo "ID $row[id]: $row[text]<br>";
     * }
     * // Calculate and display the link targets for paging.
     * $first = $table->first();
     * $prev  = $table->prev($id, $rows);
     * $next  = $table->next($id, $rows);
     * $last  = $table->prev($table->last(), ($table->count() - 1) % $rows);
     * if (strcmp($first, $id)) echo "&lt;a href=\"$PHP_SELF?id=$first\">First&lt;/a> ";
     * if ($prev)               echo "&lt;a href=\"$PHP_SELF?id=$prev\">Prev&lt;/a> ";
     * if ($next)               echo "&lt;a href=\"$PHP_SELF?id=$next\">Next&lt;/a> ";
     * if (strcmp($last, $id))  echo "&lt;a href=\"$PHP_SELF?id=$last\">Last&lt;/a>";</pre>
     *
     * Call <code>limit()</code> (or <code>limit(0)</code> or something like
     * that) to reset the limitation.
     *
     * <i>Warning! The limitation has no effect on {@link delete()},
     * {@link update()} and so on! All following method calls like {@link sort()}
     * or {@link join()} that {@link seek sets} or {@link reset resets} the
     * internal pointer will change the starting ID (but not the number of rows)
     * set by limit().</i>
     *
     * @param rows int
     * @param id mixed
     * @param whence int
     * @return bool
     * @see seek()
     */
    function limit($rows = null, $id = null, $whence = null)
    {
        // Number of rows < 1 resets the limitation.
        $this->_limitRows = $rows > 0 ? $rows : null;
        return isset($id) ? $this->seek($id, $whence) : $this->reset();
    }

    /**
     * Gets the number of rows in the table.
     *
     * @return int
     * @see count()
     */
    function num_rows()
    {
        return count($this->data);
    }

    /**
     * Gets the table name without the default .csv file extension.
     *
     * The path returned can be used in {@link MyCSV()} without any change.
     * Directories are not removed from the string, if present.
     *
     * @return string
     */
    function tablename()
    {
        return preg_replace('{^\./|\.csv$}', '', $this->filename);
    }

    /**
     * Updates a table row with some new field/value pairs.
     *
     * Examples:
     *
     * <pre>$table->update(array(...), 3);
     * $table->update(array('id' => 3, ...));
     * $table->update(array('id' => 7, ...), 3); // Moves ID 3 to ID 7</pre>
     *
     * @param data array
     * @param id mixed
     * @return bool
     */
    function update($data, $id = null)
    {
        if (!is_array($data)) return false;
        // update(array(...)) without an ID doesn't make sense.
        if (!isset($data['id']) && !isset($id)) return false;

        // update(array(...), 3) becomes update(array('id' => 3, ...), 3)
        if (!isset($data['id'])) $data['id'] = $id;
        // update(array('id' => 7, ...)) becomes update(array('id' => 7, ...), 7)
        elseif (!isset($id)) $id = $data['id'];
        // update(array('id' => 7, ...), 3) if forbidden if ID 7 already exists.
        elseif (strcmp($data['id'], $id) != 0 && isset($this->data[$data['id']]))
        {
            return false;
        }

        // Duplicate keys will be used from the new row. Due to the cast
        // update() does an insert() if required but will cause a warning.
        $this->data[$data['id']] = $data + (array)$this->data[$id];
        // update(array('id' => 7, ...), 3) moves ID 3 to 7, so ID 3 is killed.
        if (strcmp($data['id'], $id) != 0) unset($this->data[$id]);
        return true;
    }

    /**
     * Gets the number of rows in the table. This is an alias for
     * {@link num_rows()}.
     *
     * @return int
     */
    function count()
    {
        return count($this->data);
    }

    /**
     * Gets the current data row and increases the internal pointer. See
     * {@link MyCSV()} for an example.
     *
     * @return array
     */
    function each()
    {
        // Don't return more rows if the limit() is reached.
        if (isset($this->_limitRows) && --$this->_limitRows < 0) return false;
        if (!list($id, $data) = each($this->data)) return false;
        return array('id' => $id) + $data;
    }

    /**
     * Sets the internal pointer to the last data row. Returns the last data
     * row.
     *
     * @return array
     * @see reset(), last()
     */
    function end()
    {
        return end($this->data);
    }

    /**
     * Checks if the data row specified by the ID exists.
     *
     * @param id mixed
     * @return bool
     * @see row_exists()
     */
    function id_exists($id)
    {
        return isset($this->data[$id]);
    }

    /**
     * Gets an array containing all the IDs of the table.
     *
     * @return array
     * @see min(), max(), first(), last(), prev(), next(), rand()
     */
    function ids()
    {
        return array_keys($this->data);
    }

    /**
     * Sorts the table rows by ID. This is identical to
     * <code>{@link sort}("id")</code> but a bit faster.
     *
     * @param sort_flags int
     * @return void
     */
    function ksort($sort_flags = 0)
    {
        return ksort($this->data, $sort_flags);
    }

    /**
     * Sorts the table rows by ID in reverse order. This is identical to
     * <code>{@link sort}("id DESC")</code> but a bit faster.
     *
     * @param sort_flags int
     * @return void
     */
    function krsort($sort_flags = 0)
    {
        return krsort($this->data, $sort_flags);
    }

    /**
     * Gets the smallest ID number used in the table. Typically, this is 1.
     *
     * @return int
     */
    function min()
    {
        if (!$this->data) return false;
        return min(array_keys($this->data));
    }

    /**
     * Gets the biggest ID number used in the table. This is often the same as
     * {@link insert_id()} which returns the last inserted ID. But unlike that,
     * max() doesn't depend on a previous call of {@link insert()}.
     *
     * @return int
     */
    function max()
    {
        if (!$this->data) return false;
        return max(array_keys($this->data));
    }

    /**
     * Gets the first ID number from the table. This depends on how's the table
     * sorted and isn't identical to {@link min()} in all cases.
     *
     * @return int
     * @see last(), prev(), reset()
     */
    function first()
    {
        if (!$this->data) return false;
        return array_shift(array_keys($this->data));
    }

    /**
     * Gets the last ID number used in the table. This depends on how's the
     * table sorted and isn't identical to {@link max()} in all cases.
     *
     * @return int
     * @see first(), next(), end()
     */
    function last()
    {
        if (!$this->data) return false;
        return array_pop(array_keys($this->data));
    }

    /**
     * Gets the previous ID number. Use <i>offset</i> to get another ID near to
     * the row specified by <i>id</i>. Default is 1 (one backward). Returns
     * false if there is no row at this position.
     *
     * @param id mixed
     * @param offset int
     * @return int
     * @see next(), first()
     */
    function prev($id, $offset = 1)
    {
        return $this->next($id, -$offset);
    }

    /**
     * Gets the next ID number. Use <i>offset</i> to get another ID near to the
     * row specified by <i>id</i>. Default is 1 (one forward). Returns false if
     * there is no row at this position.
     *
     * @param id mixed
     * @param offset int
     * @return int
     * @see prev(), last()
     */
    function next($id, $offset = 1)
    {
        $ids = array_keys($this->data);
        //- Add sort(ids) to return the nearest smaller/bigger ID numbers.
        $i = array_search($id, $ids) + $offset;
        return isset($ids[$i]) ? $ids[$i] : false;
    }

    /**
     * Picks one or more random ID numbers out of the table.
     *
     * @param num_req int
     * @return int
     * @see ids()
     */
    function rand($num_req = 1)
    {
        return empty($this->data) ? false : array_rand($this->data, $num_req);
    }

    /**
     * Sets the internal pointer to the first data row. Returns the first data
     * row.
     *
     * @return array
     * @see end(), each(), first()
     */
    function reset()
    {
        return reset($this->data);
    }

    /**
     * Looks if a data row is already in the table.
     *
     * @param search array
     * @return bool
     * @see id_exists()
     */
    function row_exists($search)
    {
        reset($this->data);
        // foreach() destroyed the array in PHP 5.2.5.
        while (list($id, $row) = each($this->data))
        {
            reset($search);
            while (list($key, $value) = each($search))
            {
                if (!isset($row[$key]) || $row[$key] != $value) continue 2;
            }
            return true;
        }
        reset($this->data);
        return false;
    }

    /**
     * Orders the table rows by one or more columns.
     *
     * Sorting order flags:
     * - ASC or SORT_ASC - Sort in ascending order (default).
     * - DESC or SORT_DESC - Sort in descending order.
     *
     * Sorting type flags:
     * - SORT_REGULAR - Compare items normally (default).
     * - SORT_NUMERIC - Compare items numerically.
     * - SORT_STRING - Compare items as strings.
     * - SORT_LOCALE_STRING - Compare items as strings, based on the current
     *   locale. Don't forget to use setlocale() before.
     * - SORT_NAT - Compare items using a "natural order" algorithm.
     * - SORT_TIME - Compare items as date and time values. This uses
     *   strtotime() to convert the strings from the CSV file (everything in a
     *   CSV file is a string) into timestamps and compares the timestamps.
     *
     * Special condition flag: SORT_NULL - Move empty elements to the end.
     *
     * No two sorting flags of the same type can be specified after each field.
     * Some examples:
     *
     * <pre>setlocale(LC_ALL, "de_DE@euro", "de_DE", "deu_deu");
     * $table->sort("a, b DESC");
     * $table->sort("a b DESC"); // Same as above
     * $table->sort("a", "b", SORT_DESC); // Same as above
     * $table->sort("a SORT_LOCALE_STRING SORT_NULL b SORT_NULL");
     * $table->sort("a SORT_NAT, b SORT_NAT, c");</pre>
     *
     * @param sort_flags mixed
     * @return void
     */
    function sort($sort_flags)
    {
        // sort() can be called using array_multisort()-like multiple
        // parameters or a SQL-like string argument.
        if (func_num_args() > 1) $sort_flags = func_get_args();
        else $sort_flags = preg_split('/[,\s]+/s', trim($sort_flags));
        // trim(..., ", \t\n\r;") would be better but works in PHP 4.1.0+ only.

        // Reset the _cmpFields array first.
        $this->_cmpFields = array();
        $p = -1;

        // Calculate the _cmpFields array for use in _cmp().
        foreach ($sort_flags as $f)
        {
            $f = preg_replace('/^(A|DE)SC$/i', 'SORT_\0', $f);
            // Always use the integer values of predefined constants if available.
            if (defined(strtoupper($f))) $f = constant(strtoupper($f));

            // Ignore ascending order but store everything else in the associative array.
            if ($f == SORT_ASC)      continue;
            elseif ($f == SORT_DESC) $this->_cmpFields[$p]['order'] = -1;
            elseif (is_int($f))      $this->_cmpFields[$p]['type'] |= $f;
            else
            {
                ++$p;
                $this->_cmpFields[] = array('field' => $f, 'order' => 1, 'type' => 0);
            }
        }

        if (strcasecmp($this->_cmpFields[0]['field'], "id") == 0)
        {
            if ($this->_cmpFields[0]['order'] > 0) ksort($this->data);
            else krsort($this->data);
        }
        else
        {
            // Call uasort() using the _cmp() function in the class.
            uasort($this->data, array(&$this, '_cmp'));
        }

        // Reset the internal pointer.
        reset($this->data);
    }

    /**
     * Callback for use in uasort() called in sort().
     *
     * @param a array
     * @param b array
     * @return bool
     * @access private
     */
    function _cmp(&$a, &$b)
    {
        foreach ($this->_cmpFields as $f)
        {
            // Using this sorting type, empty elements always move to the end.
            if ($f['type'] & SORT_NULL)
            {
                if (strlen($a[$f['field']]) <= 0 || strlen($b[$f['field']]) <= 0)
                    $f['order'] = -1;
            }

            switch ($f['type'] & ~SORT_NULL)
            {
                case SORT_NUMERIC:
                    // Take both arguments as numbers and return their difference.
                    $result = ($a[$f['field']] - $b[$f['field']]) * $f['order'];
                    break;
                case SORT_STRING:
                    // Take both arguments as strings and use strcasecmp() for comparing.
                    $result = strcasecmp($a[$f['field']], $b[$f['field']]) * $f['order'];
                    break;
                case SORT_LOCALE_STRING:
                    // Locale based string comparison.
                    $result = strcoll(strtolower($a[$f['field']]), strtolower($b[$f['field']])) * $f['order'];
                    break;
                case SORT_NAT:
                    $result = strnatcasecmp($a[$f['field']], $b[$f['field']]) * $f['order'];
                    break;
                case SORT_TIME:
                    $result = (strtotime($a[$f['field']]) - strtotime($b[$f['field']])) * $f['order'];
                    break;
                default:
                    // By default, thrust in PHP's automatic type conversion.
                    $result = ($a[$f['field']] == $b[$f['field']]) ? 0 :
                        ($a[$f['field']] > $b[$f['field']] ? $f['order'] : -$f['order']);
                    break;
            }
            // Continue (and maybe return 0) if both arguments are equal.
            if ($result != 0)
                return $result;
        }
    }

    /**
     * Gets a table row including their ID number. Returns false if the row does
     * not exist.
     *
     * @param id mixed
     * @return array
     */
    function data($id)
    {
        return isset($this->data[$id]) ? array('id' => $id) + $this->data[$id]
            : false;
    }

    /**
     * Dumps the table to screen.
     *
     * Example:
     *
     * <pre><?php
     * require_once("MyCSV.class.php");
     * $table = new MyCSV("people");
     * $table->insert(array('name' => "Adam", 'age'  => 23));
     * $table->insert(array('name' => "Bill", 'age'  => 19));
     * echo "&lt;pre>";
     * $table->dump();
     * ?></pre>
     *
     * @return void
     * @see export()
     */
    function dump()
    {
        echo $this->export();
    }

    /**
     * Checks if the CSV file for this table already exists.
     *
     * @return bool
     */
    function exists()
    {
        return file_exists($this->filename);
    }

    /**
     * Returns a complete CSV dump of the table.
     *
     * @return string
     * @see write(), dump()
     */
    function export()
    {
        $count_fields = count($this->fields);
        $tr_from = array('"',  "\x00");
        $tr_to   = array('""', "\\\x7F");

        $csv = implode($this->delimiter, $this->fields) . "\r\n";
        reset($this->data);
        while (list($id, $row) = each($this->data))
        {
            if (strpos($id, $this->delimiter) === false &&
                strpos($id, '"') === false)
            {
                $csv .= $id;
            }
            else
            {
                $csv .= '"' . str_replace('"', '""', $id) . '"';
            }
            for ($c = 1; $c < $count_fields; ++$c)
            {
                $csv .= $this->delimiter;
                $d = @$row[$this->fields[$c]];
                if (strlen($d))
                {
                    // Add "smart" backslashes. This makes the CSV files
                    // binary-safe and compatible to PHP >=4.3.2 (which is when
                    // Ilia Alshanetsky started ruining fgetcsv).
                    $d = preg_replace('/\\\(?=\\\|\x00|"|\x7F|\x93|$)/s',
                        '\\\\\0', $d);
                    // Workaround for some more bugs in PHP 4.3.2 to 4.3.10.
                    $d = preg_replace('/(^"|"$)/s', "\\\x93", $d);
                    $csv .= '"' . str_replace($tr_from, $tr_to, $d) . '"';
                }
            }
            $csv .= "\r\n";
        }
        reset($this->data);
        return $csv;
    }

    /**
     * Checks if the CSV file for this table is writeable.
     *
     * @return bool
     */
    function is_writeable()
    {
        return is_writeable($this->filename);
    }

    /**
     * Sets the internal pointer to the data row specified by an ID or offset.
     *
     * If <i>whence</i> is left out, seek jumps to a specific ID (default).
     *
     * <i>whence</i> may be SEEK_SET to set an absolute position counted from
     * the start of the table, SEEK_CUR for a relative position or SEEK_END for
     * an absolute position counted from the end of the table. The behaviour of
     * these options is identical to fseek(). Keep in mind that <i>id</i>
     * represents an offset instead of a row ID in these cases. Example:
     *
     * <pre>$table = new MyCSV("table");
     * $table->insert(array('id' => 3)); // 1st row
     * $table->insert(array('id' => 7)); // 2nd row
     * $table->seek(1, SEEK_SET); // Jump to 2nd row
     * $row = $table->fetch_assoc();
     * echo $row['id']; // Output: 7
     * $table->seek(7); // Jump to 2nd row</pre>
     *
     * @param id mixed
     * @param whence int
     * @return bool
     * @see limit()
     */
    function seek($id = 0, $whence = null)
    {
        if (!isset($whence)) $id = array_search($id, array_keys($this->data));
        // Calculate absolute offset if end-of-file plus offset is requested.
        if ($whence == SEEK_END) $id = count($this->data) - 1 - abs($id);
        // Reset array pointer in SEEK_SET and SEEK_END mode.
        if ($whence != SEEK_CUR) reset($this->data);
        for ($i = 0; $i < $id; ++$i)
        {
            // Return false if offset is out of array bounds.
            if (!next($this->data)) return false;
        }
        return true;
    }

    /**
     * Rewrites the CSV table file or creates a new one.
     *
     * write() closes the file when done.
     *
     * The files created are binary-safe and compatible with any external spread
     * sheet software (e.g. Excel) with a few exceptions:
     * - NUL bytes (#0) are replaced with a backslash followed by a DEL
     *   character (#127). That's because older PHP versions aren't able to
     *   process CSV files containing NUL bytes.
     * - Double quotes at the beginning and end of a value are replaced with a
     *   backslash followed by a left double quote (#147). That's because newer
     *   (!) PHP versions strip such quotes.
     * - Backslashes in front of a NUL, DEL, double quote, left double quote,
     *   other backslash or end of string are replaced with two backslashes.
     * That's what I call "smart backslashes". You don't need to know about this
     * if you'r not using external software to modify your CSV files. Due to the
     * replacements described above, the class <b>is</b> able to process any
     * binary data. {@link MyCSV()} knows about these rules and undo the
     * replacements immediatelly.
     *
     * Binary safety tested with the following PHP versions: 4.3.1, 4.3.3,
     * 4.3.5, 4.3.9, 4.3.10, 4.4.0, 5.0.4.
     *
     * @param tablename string
     * @param delimiter string
     * @return bool
     */
    function write($tablename = "", $delimiter = "")
    {
        // Add default file extension if missing.
        if ($tablename && !preg_match('/\.\w+$/', $tablename))
        {
            $tablename .= ".csv";
        }
        // Close the original CSV file and prepare to create a new one.
        if ($tablename && $tablename != $this->filename)
        {
            $this->close();
            $this->filename = $tablename;
        }
        if (!$this->filename) return false;

        // Open the CSV file for exclusive writing if not opened already.
        if (!$this->_fp)
        {
            // Mode "w" is the only one who's able to create the file properly.
            $this->_fp = fopen($this->filename, "wb");
            if (!$this->_fp) return false;
            flock($this->_fp, LOCK_EX);
        }

        // Switch to another field delimiter if present.
        if ($delimiter) $this->delimiter = $delimiter;

        rewind($this->_fp);
        if (!fwrite($this->_fp, $this->export()))
        {
            // Triggers an user error if the CSV file is write-protected/read-only.
            user_error("MyCSV::write() failed, file $this->filename seems to be read only",
                E_USER_WARNING);
            return false;
        }
        ftruncate($this->_fp, ftell($this->_fp));
        $this->close();

        // Drop empty tables.
        if (count($this->fields) <= 1 && empty($this->data))
        {
            unlink($this->filename);
        }

        return true;
    }

    /**
     * @access private
     */
    function close()
    {
        if ($this->_fp)
        {
            fflush($this->_fp);
            flock($this->_fp, LOCK_UN);
            fclose($this->_fp);
            $this->_fp = false;
            if (isset($GLOBALS['_MyCSV_locked'][$this->filename]))
                unset($GLOBALS['_MyCSV_locked'][$this->filename]);
        }
    }
}
