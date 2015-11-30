<?php

/**
 * This example script for the TM::MyCSV class shows how to perform a search on
 * a table. In SQL this is known as WHERE. There is no method in the class to do
 * this. This is intended. PHP is a lot more powerfull to perform complex search
 * queries.
 *
 * @author Thiemo Mättig (http://maettig.com/)
 */

// Include the class so we can create table objects.
require_once("MyCSV.class.php");

// Create an empty table object or read the file example.csv if such a file
// exists. We do not use write() so the file example.csv is never created.
$table = new MyCSV("example");

// We need to use a constant seed for the random number generator because we
// want the same list of strings every time.
srand(0);
// Create random strings and add them to the table.
for ($i = 0; $i < 100; $i++)
{
    $table->insert(array('text' => str_shuffle("abcdefgxyz")));
}

// Check if the user entered something in the search form.
$q = isset($_REQUEST['q']) ? stripslashes($_REQUEST['q']) : "";
// Clean up the query a little bit.
$q = trim($q);

// Display the search form.
echo '<form action="' . $_SERVER['PHP_SELF'] . '" method="get">';
echo '<input name="q" type="text" value="' . htmlspecialchars($q) . '"> ';
echo '<input type="submit" value="Search">';
echo '</form>';

echo '<p>Search results:</p>';
echo '<ul>';

// Walk through all table rows, ordered by ID by default.
while ($row = $table->each())
{
    // This is how the filter is done:
    // - First, check if a search should be performed. If the query string is
    //   empty, the other comparisons are skipped and the row is displayed.
    // - Second, check if the user entered one of the ID numbers.
    // - Third, check if the query string can be found in the text field.
    if (empty($q) ||
        $row['id'] == $q ||
        stristr($row['text'], $q))
    {
        echo '<li>';
        // Even if the IDs are numbers by default, always quote HTML characters!
        echo 'Row #' . htmlspecialchars($row['id']) . ': ';
        echo htmlspecialchars($row['text']);
        echo '</li>';
    }
}

echo '</ul>';
