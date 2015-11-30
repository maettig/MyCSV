<?php

/**
 * This is a complex example script for the TM::MyCSV class. It shows how to
 * create and change a table, how to use insert(), delete() and sort().
 *
 * @author Thiemo Mättig (http://maettig.com/)
 */

// The method sort() depends on your locale settings if you use the sorting type
// flag SORT_LOCALE_STRING.
setlocale(LC_ALL, "de_DE@euro", "de_DE", "deu_deu");

// Include the PHP class first.
require_once("MyCSV.class.php");

// Read the file products.csv if exists. If you run the script for the first
// time, the write() call below will create the file if required.
$products = new MyCSV("products");

if (isset($_REQUEST['action']) && $_REQUEST['action'] == "delete")
{
    $products->delete($_REQUEST['id']);
    $products->write();
}

// Check if something was submitted in the form.
if (!empty($_POST['name']))
{
    // Add a new row to the table. If the file does not exist and the table
    // contains no fields, this call also creates the fields. Not that all
    // TM::MyCSV tables will contain an 'id' column. Every row you add to the
    // table will get a new ID (similar to AUTO_INCREMENT from MySQL).
    $products->insert(array(
        'name'  => stripslashes($_POST['name']),
        'price' => stripslashes($_POST['price']),
    ));
    // Save the whole table to disk.
    $products->write();
}

// Check if the user clicked one of the table headers.
$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : "";
$desc = isset($_REQUEST['desc']) ? " DESC" : "";

// It's a very bad idea to use something that the user entered in your SQL
// statements. This leads to SQL insertions. We do a translation instead. All
// invalid values, hacking attempts and so on will fall back to the default
// behaviour.
switch ($sort)
{
    case "name":
        // Order by name in ascending or descending order. Two products with the
        // same name will be ordered by price.
        $products->sort("name SORT_LOCALE_STRING SORT_NULL $desc, price SORT_NUMERIC");
        break;
    case "price":
        // Order by price using a numeric comparison. SORT_NULL moves products
        // without a price to the bottom.
        $products->sort("price SORT_NUMERIC SORT_NULL $desc, name SORT_LOCALE_STRING");
        break;
    default:
        // Ordered by ID ("unordered") by default.
        if (!$desc)
        {
            $products->ksort();
        }
        else
        {
            $products->krsort();
        }
        break;
}

// This is the end of the controller component of the script and the start of
// the view component. Check http://en.wikipedia.org/wiki/Model–view–controller
// if you don't know what MVC is. You don't need the frameworks mentioned there.
// The idea of MVC is simple: Prepare everything in the controller part (above
// these lines) and display everything in the view part (below these lines).
// Using code in the view part is not forbidden. However, it's forbidden to
// change data.

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="de" xml:lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1" />
<title>TM::MyCSV Example Script</title>
<meta name="robots" content="none" />
<style type="text/css">

/* A little bit of CSS to make the example look nice. */

body
{
	color: #333;
	font-family: Verdana, sans-serif;
}
form div{
	margin: 0.5em 0;
}
label
{
	cursor: pointer;
	float: left;
	padding-right: 0.6em;
	text-align: right;
	width: 10em;
}
table
{
	border-collapse: collapse;
	border-spacing: 0;
	empty-cells: show;
}
th, td
{
	border-bottom:1px solid #CCC;
	padding: 0.4em 1em;
	text-align: left;
	vertical-align: top;
}
th, th a
{
	background: #666;
	color: #FFF;
}

</style>
</head>
<body>

<h1>TM::MyCSV Example Script</h1>

<!-- In most cases I use my TM::Apeform class to create such forms. In this
     case, I don't want another dependency in this example script. -->

<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
	<fieldset>
		<legend>Add a new product</legend>
		<div>
			<label for="name">Product name:</label>
			<input id="name" name="name" type="text" />
		</div>
		<div>
			<label for="price">Price:</label>
			<input id="price" name="price" type="text" />
		</div>
		<div>
			<label>&nbsp;</label>
			<input type="submit" value="Add new product" />
		</div>
	</fieldset>
</form>

<table>
	<tr>
		<th>ID</th>
		<th>
			<a href="<?php echo $_SERVER['PHP_SELF']?>?sort=name<?php
			if ($sort == "name" && !$desc) echo '&amp;desc'?>">Product name</a><?php
			if ($sort == "name") echo $desc ? '&#x25BC;' : '&#x25B2;'?>
		</th>
		<th>
			<a href="<?php echo $_SERVER['PHP_SELF']?>?sort=price<?php
			if ($sort == "price" && !$desc) echo '&amp;desc'?>">Price</a><?php
			if ($sort == "price") echo $desc ? '&#x25BC;' : '&#x25B2;'?>
		</th>
		<th></th>
		</tr>

<?php

// Sometimes it is easier to use echo statements instead of the mixed HTML and
// PHP syntax above. I use both depending on the amount of HTML code.

while ($product = $products->each())
{
    echo '<tr>';
    echo '<td>' . htmlspecialchars($product['id']) . '</td>';
    echo '<td>' . htmlspecialchars($product['name']) . '</td>';
    echo '<td>';
    if (empty($product['price']))
    {
        echo '&mdash;';
    }
    else
    {
        echo htmlspecialchars($product['price']) . ' &euro;';
    }
    echo '</td>';
    echo '<td><a href="' . $_SERVER['PHP_SELF'] . '?action=delete&amp;id=' .
        htmlspecialchars($product['id']) . '">Delete</a></td>';
    echo '</tr>';
}

?>

</table>

</body>
</html>