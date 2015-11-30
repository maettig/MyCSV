<?php

/**
 * This example script for the TM::MyCSV class shows how easy it is to put the
 * result from a MySQL query into a CSV file. The extended class
 * MyCSV_MySQL.class.php converts data in the other direction and is not used in
 * this example.
 *
 * @author Thiemo Mättig (http://maettig.com/)
 */

require_once("MyCSV.class.php");

$csv = new MyCSV();

// Change the delimiter to ";" or "\t" if needed.
$csv->delimiter = ",";

// MySQL host, login name, password and database name.
mysql_connect("localhost", "root", "");
mysql_select_db("test");

// The SQL query can contain all combinations of WHERE, ORDER BY and so on.
$sql = "SELECT * FROM `test`";
$result = mysql_query($sql);

// Push all data into the MyCSV object.
while ($record = mysql_fetch_assoc($result))
{
    $csv->insert($record);
}

// If the delimiter is "\t", the content type should be
// "text/tab-separated-values" or "text/plain".
header("Content-Type: text/comma-separated-values");

// Dump the CSV data to screen.
$csv->dump();
