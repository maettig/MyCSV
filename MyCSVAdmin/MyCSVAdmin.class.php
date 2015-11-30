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
 * KNOWN BUGS/TODO
 * - Catch \x00 when uploading a file!
 * - Use PHP types only (null/bool/int/float/string)! Add "attribute": "signed",
 *   "unsigned", "binary".
 * - Table files aren't portable to older versions or external software when
 *   using PHP >=4.3.2 because fgetcsv() needs escaped backslashes then.
 */

define("PRIV_INSERT",    2);
define("PRIV_UPDATE",    4);
define("PRIV_DELETE",    8);
define("PRIV_CREATE",   16);
define("PRIV_DROP",     32);
define("PRIV_ALTER",   512);
define("PRIV_EXPORT", 1024);
define("PRIV_ALL",      -1);

/**
 * Universal administration tool and demo application for the TM::MyCSV class.
 *
 * Don't hesitate to
 * <a href="http://bugs.maettig.com/">report bugs or feature requests</a>.
 *
 * @author Thiemo Mättig (http://maettig.com/)
 * @version 2004-12-18
 * @package TM
 */
class MyCSVAdmin
{
    /**
     * @var string Represents the $_SERVER['PHP_SELF'] value.
     */
    var $SELF = "./";

    /**
     * @var mixed Relative path(s) where the .csv and .txt files are stored.
     */
    var $dir = "./";

    /**
     * Add the values in the following table to grand or deny access to specific
     * functions of the administrative interface. Default is PRIV_ALL,
     * everything enabled.
     *
     * - PRIV_INSERT - Insert rows
     * - PRIV_UPDATE - Update rows
     * - PRIV_DELETE - Delete rows
     * - PRIV_CREATE - Create tables
     * - PRIV_DROP - Drop tables
     * - PRIV_ALTER - Alter tables
     * - PRIV_EXPORT - Export tables</pre>
     *
     * Example:
     *
     * <pre>$admin->priv = PRIV_ALL & ~PRIV_CREATE & ~PRIV_DROP & ~PRIV_ALTER;</pre>
     *
     * @var int Privileges to grand or deny access to specific functions.
     */
    var $priv = PRIV_ALL;

    /*
     * @var string Defaults to "ISO-8859-1".
     */
    var $charset = "ISO-8859-1";

    /**
     * @var MyCSV MyCSV object currently edited by TM::MyCSVAdmin.
     */
    var $table = null;

    /**
     * @var string Contains the suggested field types for the table.
     */
    var $types = array();

    /**
     * Initializes the class. Loads the table specified by $_GET['table'] or
     * $_POST['table']. Returns a new MyCSVAdmin object.
     *
     * @param string $dir
     * @return MyCSVAdmin
     */
    function MyCSVAdmin($dir = null)
    {
        if (isset($dir)) $this->dir = $dir;

        if (isset($_SERVER['PHP_SELF'])) $this->SELF = $_SERVER['PHP_SELF'];
        elseif (isset($GLOBALS['PHP_SELF'])) $this->SELF = $GLOBALS['PHP_SELF'];
        $this->SELF = str_replace("index.php", "", $this->SELF);

        if ($this->GET('table'))
        {
            $this->table = new MyCSV($this->GET('table'));
            $this->_getTypes();
        }
    }

    /**
     * Gets a GET or POST value. This unifies the different behaviours of $_GET,
     * $HTTP_GET_VARS, $GLOBALS (register_globals) and so on.
     *
     * @param string $key Name of the request variable to be returned.
     * @return string
     */
    function GET($key)
    {
        if (isset($_GET[$key])) $value = $_GET[$key];
        elseif (isset($GLOBALS['HTTP_GET_VARS'][$key])) $value = $GLOBALS['HTTP_GET_VARS'][$key];
        elseif (isset($_POST[$key])) $value = $_POST[$key];
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$key])) $value = $GLOBALS['HTTP_POST_VARS'][$key];
        else return "";
        if (get_magic_quotes_gpc()) return stripslashes($value);
        return $value;
    }

    /**
     * Makes a string well readable to the browser. It strips any critical
     * characters, truncates the string and replaces HTML entities.
     *
     * @param string $text Text to be HTMLed.
     * @return string
     */
    function htmlQuote($text)
    {
        if (strlen($text) > 100) $text = substr($text, 0, 80) . "...";
        $text = preg_replace(
            '/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F\x81\x83\x86-\x8F\x90\x98-\x9F]/',
            '.', $text);
        $text = wordwrap($text, 20, "\n", true);
        return htmlentities($text, ENT_NOQUOTES, $this->charset);
    }

    /**
     * Tries to guess the field types based on what values are in the table
     * rows.
     *
     * @return void
     * @access private
     */
    function _getTypes()
    {
        $types = array(0 => "null", 1 => "bool", 2 => "int", 3 => "float",
            4 => "varchar", 5 => "text", 6 => "blob");
        $tLeast = $this->table->count() < 2 ? 4 : 0;
        if ($this->GET('override')) { $types[1] = "int"; $types[4] = "text"; $types[6] = "text"; }
        foreach ($this->table->fields as $field)
        {
            $t[$field] = strcasecmp($field, "id") ? $tLeast : 2;
        }
        while ($row = $this->table->each())
        {
            foreach ($row as $field => $value)
            {
                if (preg_match('/[\x00-\x06\x08\x0B-\x0C\x0E-\x13\x16-\x1F]/', $value))
                    $t[$field] = 6;
                elseif (strlen($value) > 255 || strpos($value, "\n"))
                    $t[$field] = max($t[$field], 5);
                elseif (! empty($value) && ! is_numeric($value))
                    $t[$field] = max($t[$field], 4);
                elseif (! preg_match('/^[+-]?\d{0,10}$/s', $value))
                    $t[$field] = max($t[$field], 3);
                elseif (! preg_match('/^[01]?$/s', $value))
                    $t[$field] = max($t[$field], 2);
                elseif (strlen($value))
                    $t[$field] = max($t[$field], 1);
            }
        }
        foreach ($this->table->fields as $field)
        {
            $this->types[$field] = $types[$t[$field]];
        }
        $this->table->reset();
    }

    /**
     * @param data string
     * @return string
     * @access private
     */
    function _mime_content_type($data)
    {
        // See magic.mime
        if (substr($data, 0, 2) == "\xFF\xD8") return 'image/jpeg';
        elseif (substr($data, 1, 3) == 'PNG') return 'image/png';
        elseif (substr($data, 0, 4) == 'GIF8') return 'image/gif';
        else return 'application/octet-stream';
    }

    /**
     * @return void
     * @access private
     */
    function displayHead()
    {
        echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta http-equiv="content-type" content="text/html; charset=' . $this->charset . '">';
        echo '<title>TM::MyCSVAdmin';
        if (isset($this->table))
        {
            $table = $this->table->tablename();
            echo ' - Table ' . htmlspecialchars(basename($table));
        }
        echo '</title>';
        echo '<meta name="robots" content="noindex,nofollow">';
        echo '<style type="text/css">';
        echo 'h1{font-size:150%;margin-top:0;}';
        echo '.subtitle{font-size:x-small;font-weight:normal;}';
        echo 'ul#menu{border-bottom:2px solid #CFE8CF;margin-left:0;padding:0;}';
        echo 'ul#menu li{display:inline;}';
        echo 'ul#menu li a{background:#F0F7F0;border:solid #CFE8CF;border-width:1px 1px 0 1px;margin-left:10px;padding:0 10px;text-align:center;width:70px;}';
        echo 'th{background-color:#CFE8CF;}';
        echo 'td{background-color:#F0F7F0;vertical-align:top;}';
        echo 'small{color:#666;}';
        echo 'acronym{border-bottom:1px dotted #666;}';
        echo '.primary{color:#960;font-weight:bold;text-decoration:underline;}';
        echo '.error{color:#C00;}';
        echo '.danger:hover,#menu .danger:hover{background-color:#C30;color:white;text-decoration:none;}';
        echo '.download,.override{text-decoration:none;}';
        echo 'a:hover{color:#C30;text-decoration:underline;}';
        echo '#menu a:hover{background-color:#CFE8CF;}';
        echo 'tr:hover td{background-color:#CFE8CF;}';
        echo '</style>';
        if (file_exists("MyCSVAdmin.css"))
        {
            echo '<link rel="stylesheet" type="text/css" href="MyCSVAdmin.css">';
        }
        echo '</head>';
        echo "<body bgcolor=\"white\" text=\"black\" link=\"#006600\" alink=\"#CC3300\" vlink=\"#006600\">\n\n";

        $script = preg_replace('{[^/]+(/index)?\.\w+$}', '', getenv('SCRIPT_NAME'));
        echo '<a class="subtitle" href="' . $script . '">Go to parrent site</a>';
        echo '<h1>TM::<a href="' . $this->SELF . '">MyCSVAdmin</a>';
        if (isset($this->table))
        {
            echo ' &ndash; Table <a href="' . $this->SELF . '?method=structure&table=' . htmlspecialchars($table) . '">';
            echo '<em>' . htmlspecialchars(basename($table)) . '</em></a>';
        }
        echo '</h1>';

        if (isset($this->table))
        {
            echo '<ul id="menu">';
            echo '<li><a href="' . $this->SELF . '?method=browse&table=' . $table . '">Browse</a></li>';
            if ($this->priv & PRIV_INSERT)
                echo '<li><a href="' . $this->SELF . '?method=change&table=' . $table . '">Insert</a></li>';
            echo '<li><a href="' . $this->SELF . '?method=structure&table=' . $table . '">Structure</a></li>';
            if ($this->priv & PRIV_EXPORT)
                echo '<li><a href="' . $this->SELF . '?method=export&table=' . $table . '">Export</a></li>';
            if ($this->priv & PRIV_DROP)
                echo '<li><a class="danger" href="' . $this->SELF . '?method=delete_all&table=' . $table . '">Empty</a></li>';
            if ($this->priv & PRIV_DROP)
                echo '<li><a class="danger" href="' . $this->SELF . '?method=drop_table&table=' . $table . '">Drop</a></li>';
            echo '</ul>';
        }
    }

    /**
     * Shows a list of all tables. Use {@link dir} to specify the working
     * directory.
     *
     * @return void
     */
    function tables()
    {
        $form = new Apeform(0, 0, false);
        $table = $form->text("<u>T</u>able name");
        if (! preg_match('{[/\\\]}', $table))
        {
            $dir = (array)$this->dir;
            if (! preg_match('{[/\\\]$}', $dir[0])) $dir[0] .= "/";
            $table = $dir[0] . $table;
        }
        $form->submit("Create new table");
        if ($form->isValid())
        {
            $this->table = new MyCSV($table);
            // Jump to the table structure.
            $this->structure();
            return;
        }
        
        $tables = array();
        foreach ((array)$this->dir as $dir)
        {
            if (! preg_match('{[/\\\]$}', $dir)) $dir .= "/";
            $fp = opendir($dir);
            while (($filename = readdir($fp)) !== false)
            {
                $filename = $dir . $filename;
                if (! is_file($filename)) continue;
                if (! preg_match('/\.([a-z]sv|txt)$/i', $filename)) continue;
                $tables[] = $filename;
            }
            closedir($fp);
        }
        usort($tables, 'strcasecmp');

        $this->displayHead();

        echo '<table>';
        echo '<tr><th>Table</th><th colspan="6">Action</th><th>Rows</th><th>Size</th></tr>';
        $count = 0;
        $sumRecords = 0;
        $sumSize = 0;
        foreach ($tables as $filename)
        {
            $csv = new MyCSV($filename);
            $table = $csv->tablename();
            $count++;
            $sumRecords += $records = $csv->num_rows();
            $sumSize += $size = filesize($filename);
            $csv->close();

            echo '<tr><td>' . htmlspecialchars(basename($table)) . '</td>';
            echo '<td><a href="' . $this->SELF . '?method=browse&table=' . $table . '">Browse</a></td>';
            echo '<td>';
            if ($this->priv & PRIV_INSERT)
                echo '<a href="' . $this->SELF . '?method=change&table=' . $table . '">Insert</a>';
            echo '</td>';
            echo '<td><a href="' . $this->SELF . '?method=structure&table=' . $table . '">Structure</a></td>';
            echo '<td>';
            if ($this->priv & PRIV_EXPORT)
                echo '<a href="' . $this->SELF . '?method=export&table=' . $table . '">Export</a>';
            echo '</td><td>';
            if ($this->priv & PRIV_DROP)
                echo '<a class="danger" href="' . $this->SELF . '?method=delete_all&table=' . $table . '">Empty</a>';
            echo '</td><td>';
            if ($this->priv & PRIV_DROP)
                echo '<a class="danger" href="' . $this->SELF . '?method=drop_table&table=' . $table . '">Drop</a>';
            echo '</td>';
            echo '<td align="right">' . number_format($records) . '</td>';
            echo '<td align="right">' . number_format($size / 1024, 1) . ' KB</td>';
            echo '</tr>';
        }
        echo '<tr>';
        echo '<th>' . $count . ' tables</th>';
        echo '<th colspan="6">Sum</th>';
        echo '<th align="right">' . number_format($sumRecords) . '</th>';
        echo '<th align="right">' . number_format($sumSize / 1024, 1) . ' KB</th>';
        echo '</tr>';
        echo '</table>';

        if ($this->priv & PRIV_CREATE) $form->display();
    }
    
    /**
     * Shows the structure of a table.
     *
     * @return void
     */
    function structure()
    {
        $form = new Apeform(0, 0, false);
        $form->hidden("structure", "method");
        $form->hidden($this->table->tablename(), "table");
        $field = &$form->hidden($this->GET('method') == "structure" ? $this->GET('field') : "");
        $newField = &$form->text("<u>F</u>ield name", "", $field);

        foreach ($this->table->fields as $key) $positions[$key] = "After " . $key;
        $keys = array_keys($positions);
        $defaultValue = ($field && in_array($field, $keys)) ? $keys[array_search($field, $keys) - 1] : "";
        if ($field) unset($positions[$field]);
        array_pop($positions);
        $positions[''] = "At end of table";
        $label = $field ? "Move to <u>p</u>osition" : "Add at <u>p</u>osition";
        $afterField = $form->select($label, "", $positions, $defaultValue);

        $button = $field ? "Rename/move field" : "Add new field";
        $form->submit($button);

        if ($form->isValid())
        {
            if (! $field)
            {
                if ($this->table->add_field($newField, $afterField)) $this->table->write();
                else $form->error("Invalid field name", 3);
            }
            else
            {
                while ($row = $this->table->each())
                {
                    // Copy all the moved values to their new field name.
                    $this->table->data[$row['id']][$newField] = $row[$field];
                }
                $newFields = array();
                foreach ($this->table->fields as $oldField)
                {
                    // Copy any unchanged field to the new fields array.
                    if ($oldField != $field) $newFields[] = $oldField;
                    if ($oldField == $afterField) $newFields[] = $newField;
                }
                // Add field if position '' ("At end of table") was selected.
                if (! $afterField) $newFields[] = $newField;
                $this->table->fields = $newFields;
                $this->table->write();
            }
            $field = "";
            $newField = "";
        }

        $formOrder = new Apeform(0, 0, "order", false);
        $formOrder->hidden("structure", "method");
        $formOrder->hidden($this->table->tablename(), "table");
        $order = $formOrder->select("Permanently <u>o</u>rder table by", "",
            $this->table->fields);
        $formOrder->submit("Order");

        if ($formOrder->isValid())
        {
            $this->table->sort($this->table->fields[$order]);
            $this->table->write();
        }

        $this->displayHead();

        echo '<table>';
        echo '<tr><th>Field</th><th><small>Guessed type</small></th><th colspan="2">Action</th></tr>';
        foreach ($this->table->fields as $i => $f)
        {
            echo '<tr><td';
            if (! $i) echo ' class="primary" title="Row identifier"';
            echo '>' . $f . '</td>';
            echo '<td><small>' . (isset($this->types[$f]) ? $this->types[$f] : "") . '</small></td>';
            echo '<td>';
            if ($i && $this->priv & PRIV_ALTER)
                echo '<a href="' . $this->SELF . '?method=structure&table=' . $this->table->tablename() . '&field=' . $f . '">Change</a>';
            echo '</td><td>';
            if ($i && $this->priv & PRIV_ALTER)
                echo '<a class="danger" href="' . $this->SELF . '?method=drop&table=' . $this->table->tablename() . '&field=' . $f . '">Drop</a>';
            echo '</td></tr>';
        }
        echo '</table>';

        if ($this->priv & PRIV_ALTER) $form->display();
        if ($this->priv & PRIV_UPDATE) $formOrder->display();

        echo '<p><table>';
        echo '<tr><th>Statement</th><th>Value</th></tr>';
        echo '<tr><td>Rows</td><td align="right">' . $this->table->num_rows() . '</td></tr>';
        echo '<tr><td>Next autoindex</td><td align="right">' . ($this->table->num_rows() ? max($this->table->ids()) + 1 : 1) . '</td></tr>';
        $filesize = $this->table->exists() ? filesize($this->table->filename) : 0;
        $filemtime = $this->table->exists() ? date("Y-m-d H:i", filemtime($this->table->filename)) : "";
        echo '<tr><td>File size</td><td align="right">' . number_format($filesize) . ' Bytes</td></tr>';
        $size = $this->table->num_rows()
            ? (int)round(($filesize - strlen(implode(",", $this->table->fields)) - 2) / $this->table->num_rows())
            : 0;
        echo '<tr><td>Average row size</td><td align="right">' . $size . ' Bytes</td></tr>';
        echo '<tr><td>Delimiter</td><td align="right">' . $this->table->delimiter . '</td></tr>';
        echo '<tr><td>Last update</td><td align="right">' . $filemtime . '</td></tr>';
        echo '</table>';
    }

    /**
     * Shows the contents of a table.
     *
     * @return void
     */
    function browse()
    {
        $sort = $this->GET('sort');
        if ($sort) $this->table->sort($sort . " SORT_NULL");
        $rows = (int)$this->GET('rows');
        if (empty($rows) || $rows < 1) $rows = 50;

        if ($this->table->count() > $rows)
        {
            $id = $this->GET('id');
            if (empty($id)) $id = $this->table->first();
            $this->table->limit($rows, $id);

            $form = '<form action="' . $this->SELF . '" method="get"><p>';
            $aHref = '<a href="' . $this->SELF . '?method=browse&amp;table=' . $this->table->tablename() . '&amp;sort=' . urlencode($sort) . '&amp;rows=' . $rows . '&amp;id=';
            $first = $this->table->first();
            $prev  = $this->table->prev($id, $rows);
            $next  = $this->table->next($id, $rows);
            $last  = $this->table->prev($this->table->last(), ($this->table->count() - 1) % $rows);
            if (strcmp($first, $id) == 0) $form .= '&lt;&lt; First | ';
            else $form .= $aHref . urlencode($first) . '">&lt;&lt; First</a> | ';
            if ($prev === false) $form .= '&lt; Previous | ';
            else $form .= $aHref . urlencode($prev) . '">&lt; Previous</a> | ';
            $form .= '<input name="method" type="hidden" value="browse">';
            $form .= '<input name="table" type="hidden" value="' . $this->table->tablename() . '">';
            $form .= '<input name="sort" type="hidden" value="' . htmlspecialchars($sort) . '">';
            $form .= '<input type="submit" value="Show"> ';
            $form .= '<input name="rows" size="4" type="text" value="' . $rows . '"> rows starting from <span class="primary" title="Row identifier">id</span> ';
            $form .= '<input name="id" size="12" type="text" value="' . htmlspecialchars($id) . '"> | ';
            if ($next === false) $form .= 'Next &gt; | ';
            else $form .= $aHref . urlencode($next) . '">Next &gt;</a> | ';
            if (strcmp($last, $id) == 0) $form .= 'Last &gt;&gt;';
            else $form .= $aHref . urlencode($last) . '">Last &gt;&gt;</a>';
            $form .= '</p></form>';
        }

        $this->displayHead();
        if (! empty($form)) echo $form;

        echo '<table>';
        echo '<tr><th colspan="2"></th>';
        foreach ($this->table->fields as $i => $field)
        {
            $field = $this->htmlQuote($field);
            echo '<th><a href="' . $this->SELF;
            echo '?method=browse&amp;table=' . $this->table->tablename() . '&amp;sort=' . urlencode($field);
            if ($sort == $field) echo urlencode(' DESC');
            echo '&amp;rows=' . urlencode($rows);
            echo '" title="Order"';
            if (! $i) echo ' class="primary"';
            echo '>' . $field . '</a>';
            if (strcmp($sort, $field) == 0) echo '+';
            elseif (substr($sort, 0, -5) == $field) echo '-';
            echo '</th>';
        }
        echo '</tr>';

        while ($row = $this->table->each())
        {
            echo '<tr><td>';
            if ($this->priv & PRIV_UPDATE)
                echo '<a href="' . $this->SELF . '?method=change&table=' . $this->table->tablename() . '&id=' . urlencode($row['id']) . '">Edit</a>';
            echo '</td><td>';
            if ($this->priv & PRIV_DELETE)
                echo '<a class="danger" href="' . $this->SELF . '?method=delete&table=' . $this->table->tablename() . '&id=' . urlencode($row['id']) . '">Delete</a>';
            echo '</td>';
            foreach ($this->table->fields as $field)
            {
                if (! isset($row[$field])) $row[$field] = "";
                switch ($this->types[$field])
                {
                    case "bool":
                        echo '<td align="center">' . ($row[$field] ? '&times;' : '') . '</td>';
                        break;
                    case "int":
                    case "float":
                        echo '<td align="right">' . $row[$field] . '</td>';
                        break;
                    case "blob":
                        echo '<td><a href="' . $this->SELF;
                        echo '?method=download&amp;table=' . $this->table->tablename();
                        echo '&amp;id=' . urlencode($row['id']) . '&amp;field=' . $field . '"';
                        echo ' class="download" title="Download binary file">';
                        echo $this->htmlQuote($row[$field]) . '</a></td>';
                        break;
                    default:
                        echo '<td>' . $this->htmlQuote($row[$field]) . '</td>';
                }
            }
            echo '</tr>';
        }
        echo '</table>';

        if (! empty($form)) echo $form;
        if ($this->priv & PRIV_INSERT)
            echo '<p><a href="' . $this->SELF . '?method=change&table=' . $this->table->tablename() . '">Insert new row</a></p>';
    }
    
    /**
     * Shows a form to insert or update a row.
     *
     * @return void
     */
    function change()
    {
        $form = new Apeform(10000, 60, false);
        $form->hidden("change", "method");
        $table = $form->hidden($this->table->tablename(), "table");
        $id = &$form->hidden($this->GET('id'), "id");
        $form->hidden($this->GET('override'), "override");
        $row = $this->table->data($id);

        $function = basename($this->table->tablename()) . "_onRowLoaded";
        if (function_exists($function))
        {
            $function(&$row, &$this->table);
        }

        foreach ($this->table->fields as $i => $field)
        {
            $label = $i ? $field : ('<span class="primary" title="Row identifier">' . $field . '</span>');
            $help = "";
            if (! $this->GET('override'))
            {
                if ($field == "id") $help = "Primary key";
                elseif ($this->types[$field] == "null") $help = "";
                else $help = "Guessed type: " . $this->types[$field];
                if ($this->types[$field] == "bool" || $this->types[$field] == "varchar" || $this->types[$field] == "blob")
                {
                    $help .= ' (<a class="override" href="' . $this->SELF . '?method=change';
                    $help .= '&table=' . $table . '&id=' . $id . '&override=1">override</a>)';
                }
            }
            $value = isset($row[$field]) ? $row[$field] : "";

            $function = basename($this->table->tablename()) . "_" . $field .
                "_onDisplay";

            if (! $this->GET('override') && preg_match('/^(.+)_id$/i', $field, $matches))
            {
                $tablename = dirname($this->table->tablename());
                if ($tablename) $tablename .= "/";
                $tablename .= $matches[1];
                $foreignTable = new MyCSV($tablename);
                if ($foreignTable->exists())
                {
                    $fField = $foreignTable->fields[1];
                    $foreignTable->sort($fField);
                    $options = array();
                    while ($fRow = $foreignTable->each())
                    {
                        $options[$fRow['id']] = substr($fRow['id'] . " (" . $fRow[$fField], 0, $form->size - 4) . ")";
                    }
                    $help = 'Foreign key (<a class="override" href="' . $this->SELF . '?method=change';
                    $help .= '&table=' . $table . '&id=' . $id . '&override=true">override</a>)';
                    $changedRow[$field] = $form->select($label, $help, $options, $value);
                    $foreignTable->close();

                    if (function_exists($function))
                    {
                        $function(&$form->_rows[count($form->_rows) - 1], &$row);
                    }
                    continue;
                }
            }

            switch ($this->types[$field])
            {
                case "bool":
                    $changedRow[$field] = $form->checkbox($label, $help,
                        array(1 => ""), $value);
                    break;
                case "int":
                case "float":
                    $changedRow[$field] = $form->text($label, $help, $value, 0,
                        10);
                    break;
                case "text":
                    $changedRow[$field] = $form->textarea($label, $help, $value,
                        7);
                    break;
                case "blob":
                    if ($file = $form->file($label, $help))
                    {
                        $fp = fopen($file['tmp_name'], "rb");
                        $changedRow[$field] = fread($fp, 10000);
                        fclose($fp);
                    }
                    break;
                default:
                    $changedRow[$field] = $form->text($label, $help, $value);
            }

            if (function_exists($function))
            {
                $function(&$form->_rows[count($form->_rows) - 1], &$row);
            }
        }
        // Show these both Buttons in edit/update() mode.
        $button = array();
        if ($this->priv & PRIV_UPDATE) $button[] = "Save changes";
        if ($this->priv & PRIV_INSERT) $button[] = "Save as new row";
        if ($this->priv & PRIV_DELETE) $button[] = "Delete";
        // Show this Button in insert() mode.
        if (empty($changedRow['id'])) $button = "Insert new row";
        $button = $form->submit($button);

        if ($form->isValid() && strstr($button, " new row") &&
            $this->table->id_exists($changedRow['id']))
        {
            $form->error('Duplicate entry for <span class="primary">id</span>',
                4);
        }
        if ($form->isValid())
        {
            if ($button == "Delete") { $this->delete(); return; }

            $function = basename($this->table->tablename()) . "_onRowValidated";
            if (function_exists($function))
            {
                $function(&$changedRow, &$this->table);
            }

            if ($button == "Save as new row") $id = "";
            if ($id != "") $this->table->update($changedRow, $id);
            else $this->table->insert($changedRow);

            $function = basename($this->table->tablename()) . "_onRowUpdated";
            if (function_exists($function))
            {
                $function(&$this->table);
            }

            $this->table->write();
            $id = $changedRow['id'];
            if ($id == "") { $this->browse(); return; }
        }

        $this->displayHead();

        $form->display();
    
        if ($id != "")
        {
            echo '<p>';
            $aHref = '<a href="' . $this->SELF . '?method=change&table=' . $table;
            if ($this->table->prev($id) === false)
                echo '&lt;&lt; First | &lt; Previous | ';
            else
            {
                echo $aHref . '&id=' . urlencode($this->table->first()) . '">&lt;&lt; First</a> | ';
                echo $aHref . '&id=' . urlencode($this->table->prev($id)) . '">&lt; Previous</a> | ';
            }
            if ($this->table->next($id) === false)
                echo 'Next &gt; | Last &gt;&gt;';
            else
            {
                echo $aHref . '&id=' . urlencode($this->table->next($id)) . '">Next &gt;</a> | ';
                echo $aHref . '&id=' . urlencode($this->table->last()) . '">Last &gt;&gt;</a>';
            }
            echo '</p>';
        }
    }

    /**
     * Shows a form to export the table.
     *
     * @return void
     */
    function export()
    {
        $form = new Apeform(0, 0, false);
        $form->hidden("export", "method");
        $table = $form->hidden($this->table->tablename(), "table");
        $options = array(
            'csv' => '<acronym title="Comma Separated Values"><u>C</u>SV</acronym>/TXT',
            'sql' => '<acronym title="Structured Query Language"><u>S</u>QL</acronym> dump',
            'xml' => '<acronym title="Extensible Markup Language"><u>X</u>ML</acronym> <span style="font-size:smaller">(<a href="http://www.google.de/search?q=FlatXmlDataSet">FlatXmlDataSet</a>)</span>');
        $type = $form->radio("Export as", "", $options, "csv");
        $options = array(
            "," => "Comma (,)",
            ";" => "Semikolon (;)",
            "\t" => "Tabulator",
            "\\0" => "Nul",
            "|" => "Pipe (|)",
            "&" => "Ampersand (&amp;)",
            ":" => "Colon (:)",
            " " => "Space");
        $delimiter = $form->select("CSV/TXT <u>d</u>elimiter", "", $options, ",");
        if ($delimiter == "\\0") $delimiter = chr(0);
        $sections = $form->checkbox("SQL sections", "", "Structure|Data", "Structure|Data");
        $save = $form->checkbox("Save as <u>f</u>ile");
        if (function_exists("gzencode")) $compress = $form->checkbox("Save <u>g</u>zip compressed");
        $form->submit("Export");

        $tablename = preg_replace('/\W+/', '_', basename($this->table->tablename()));

        if ($form->isValid() && $type == "csv")
        {
            $this->table->delimiter = $delimiter;
            $export = $this->table->export();
        }
        elseif ($form->isValid() && $type == "sql")
        {
            $export = "CREATE TABLE `" . $tablename . "` (\n";
            foreach ($this->table->fields as $field)
            {
                $export .= "  `" . preg_replace('/\W+/', '_', $field) . "` ";
                if ($this->types[$field] == "varchar" || $this->types[$field] == "null") $export .= "VARCHAR(255)";
                elseif ($this->types[$field] == "bool") $export .= "TINYINT";
                else $export .= strtoupper($this->types[$field]);
                $export .= " NOT NULL";
                if ($field == "id") $export .= " AUTO_INCREMENT";
                $export .= ",\n";
            }
            $export .= "  PRIMARY KEY (`id`)\n);\n\n";
            if (! in_array("Structure", $sections)) $export = "";
            if (! in_array("Data", $sections)) $this->table->delete();
            while ($row = $this->table->each())
            {
                foreach ($row as $field => $value)
                {
                    $sqlRow[preg_replace('/\W+/', '_', $field)] = "'" . addslashes($value) . "'";
                }
                $export .= "INSERT INTO `" . $tablename;
                $export .= "` (`" . implode("`, `", array_keys($sqlRow)) . "`) VALUES (" . implode(", ", $sqlRow) . ");\n";
            }
        }
        elseif ($form->isValid() && $type == "xml")
        {
            $export = '<?xml version="1.0" encoding="' . $this->charset . "\"?>\n";
            $export .= "<dataset>\n";
            while ($row = $this->table->each())
            {
                $export .= '  <' . $tablename . ' id="' . $row['id'] . '"';
                unset($row['id']);
                foreach ($row as $field => $value)
                {
                    $field = preg_replace('/[^a-z0-9-]+/i', '_', $field);
                    if (! preg_match('/^[a-z]/i', $field)) $field = "field" . $field;
                    $export .= ' ' . $field . '="' . htmlspecialchars($value) . '"';
                }
                $export .= " />\n";
            }
            $export .= "</dataset>\n";
        }

        if ($save)
        {
            $mimeType = 'text/comma-separated-values';
            $extension = "." . $type;
            if ($type == "csv" && $delimiter == "\t")
            {
                $mimeType = 'text/tab-separated-values';
                $extension = ".txt";
            }
            elseif ($type == "sql") $mimeType = 'application/octet-stream';
            elseif ($type == "xml") $mimeType = 'text/xml';
            if (isset($compress) && $compress)
            {
                $export = gzencode($export);
                $mimeType = 'application/x-gzip';
                $extension .= ".gz";
            }
            $filename = basename($this->table->tablename()) . $extension;
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . strlen($export));
            header('Content-Disposition: attachement; filename="' . $filename . '";');
            echo $export;
            return;
        }

        $this->displayHead();

        $form->display();

        echo "<pre>";
        if (isset($export)) echo htmlentities($export, ENT_NOQUOTES, $this->charset);
    }

    /**
     * Download binary file specified by tablename, id and field.
     *
     * @return void
     */
    function download()
    {
        $field = $this->GET('field');
        $id = $this->GET('id');
        $file = $this->table->data[$id][$field];
        header('Content-Type: ' . $this->_mime_content_type($file));
        header('Content-Length: ' . strlen($file));
        header('Content-Disposition: attachment; filename="' . $field . '_' . $id . '";');
        echo $file;
    }

    /**
     * Confirms the deletion of a row.
     *
     * @return void
     */
    function delete()
    {
        if ($this->GET('sure'))
        {
            $function = basename($this->table->tablename()) . "_onRowDelete";
            if (function_exists($function))
            {
                $function($this->table->data($this->GET('id')), &$this->table);
            }

            $this->table->delete($this->GET('id'));
            $this->table->write();
            $this->browse();
            return;
        }
        $this->displayHead();
        echo 'Do you really want to<br>delete row \'' . $this->GET('id') . '\'?<p>';
        echo '<a class="danger" href="' . $this->SELF . '?method=delete&table=' . $this->table->tablename() . '&id=' . $this->GET('id') . '&sure=1">Yes</a> | ';
        echo '<a href="' . $this->SELF . '?method=browse&table=' . $this->table->tablename() . '">No</a>';
    }

    /**
     * Confirms the deletion of all rows.
     *
     * @return void
     */
    function delete_all()
    {
        if ($this->GET('sure'))
        {
            $this->table->delete();
            $this->table->write();
            $this->structure();
            return;
        }
        $this->displayHead();
        echo 'Do you really want to<br>delete all rows from table `' . $this->table->tablename() . '`?<p>';
        echo '<a class="danger" href="' . $this->SELF . '?method=delete_all&table=' . $this->table->tablename() . '&sure=1">Yes</a> | ';
        echo '<a href="' . $this->SELF . '?method=browse&table=' . $this->table->tablename() . '">No</a>';
    }
    
    /**
     * Confirms the deletion of a column.
     *
     * @return void
     */
    function drop()
    {
        if ($this->GET('sure'))
        {
            $this->table->drop_field($this->GET('field'));
            $this->table->write();
            $this->structure();
            return;
        }
        $this->displayHead();
        echo 'Do you really want to<br>drop field `' . $this->GET('field') . '`?<p>';
        echo '<a class="danger" href="' . $this->SELF . '?method=drop&table=' . $this->table->tablename() . '&field=' . $this->GET('field') . '&sure=1">Yes</a> | ';
        echo '<a href="' . $this->SELF . '?method=structure&table=' . $this->table->tablename() . '">No</a>';
    }

    /**
     * Confirms the deletion of a table.
     *
     * @return void
     */
    function drop_table()
    {
        if ($this->GET('sure'))
        {
            $this->table->drop_table();
            $this->table->write();
            unset($this->table);
            $this->tables();
            return;
        }
        $this->displayHead();
        echo 'Do you really want to<br>drop table `' . $this->table->tablename() . '`?<p>';
        echo '<a class="danger" href="' . $this->SELF . '?method=drop_table&table=' . $this->table->tablename() . '&sure=1">Yes</a> | ';
        echo '<a href="' . $this->SELF . '?method=browse&table=' . $this->table->tablename() . '">No</a>';
    }
}

?>