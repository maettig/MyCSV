<?php

/*
 * Collection of test cases for the TM::MyCSV class. Requires PEAR::PHPUnit 1,
 * see http://pear.php.net/package/PHPUnit/
 */

require_once("MyCSV.class.php");
require_once("PHPUnit.php");
require_once("PHPUnit/GUI/HTML.php");

class MyCSVTest extends PHPUnit_TestCase
{
    var $t = null;
    var $tMixed = null;

    function setUp()
    {
        $this->t = new MyCSV();
        $this->t->insert(array('id' => 8));
        $this->t->insert(array('id' => 2));
        $this->t->insert(array('id' => 15));
        $this->t->insert(array('id' => 3));
        $this->t->insert(array('id' => 19));
        $this->t->insert(array('id' => 9));
        $this->t->insert(array('id' => 4));
        $this->t->insert(array('id' => 11));
        $this->t->each();
        $this->t->each();
        $this->t->each();

        $this->tMixed = new MyCSV();
        $this->tMixed->add_field("a");
        $this->tMixed->add_field("b");
        $this->tMixed->add_field("c");
        $this->tMixed->insert(array('a' => "A1", 'b' => "38", 'c' => 12));
        $this->tMixed->insert(array('a' => "A2", 'b' => "27", 'c' => "12"));
        $this->tMixed->insert(array('a' => "A3", 'b' => "42", 'c' => "111"));
        $this->tMixed->insert(array('id' => 5, 'a' => "A5", 'b' => "13", 'c' => 15));
        $this->tMixed->insert(array('id' => "x", 'a' => "Ax", 'b' => "31", 'c' => 11.0));
    }

    function tearDown()
    {
        $this->t = null;
        $this->tMixed = null;
    }

    function testConstants()
    {
        $this->assertNotNull(SORT_NAT);
        $this->assertTrue(SORT_NAT > SORT_ASC);
        $this->assertNotNull(SORT_NULL);
        $this->assertTrue(SORT_NULL > SORT_ASC);
    }

    function testMyCSV()
    {
        $t = new MyCSV();
        $this->assertEquals(array("id"), $t->fields);
        $this->assertEquals(array(), $t->data);
        $this->assertEquals(",", $t->delimiter);
    }

    function testBinary()
    {
        $binaries = array(
            "a", str_repeat("a", 1000), "", "a,a", ",", ";",
            "\0", "\x00", chr(0), "\0\0\0\0", "\\\0", "\x7F", "\\\x7F",
            "\",\"", "a \"a\" a", "\"a\"", "\"", "\"\"", "\\\"a\"", "\\\\\"a\"",
            "\\\x13\"\x13\\\x13\\\\\x13\\", "a\\\"\"", "\x93", "\\\x93\"",
            "\\\"\x93", '\\\\\\' . chr(0) . '\\' . chr(127) . '""\\');
        $tablename = uniqid("tmp") . ".csv";
        $a = new MyCSV($tablename);
        foreach ($binaries as $id => $binary)
        {
            $a->insert(array('id' => $id, 'mid' => $binary, 'end' => $binary));
        }
        $a->write();
        $b = new MyCSV($tablename);
        foreach ($binaries as $id => $binary)
        {
            $this->assertEquals($binary, $a->data[$id]['mid'], $id . ' mid org:');
            $this->assertEquals($binary, $a->data[$id]['end'], $id . ' end org:');
            $this->assertEquals($binary, $b->data[$id]['mid'], $id . ' mid:');
            $this->assertEquals($binary, $b->data[$id]['end'], $id . ' end:');
        }
        $b->close();
        unlink($tablename);
    }

    function testCount()
    {
        $this->assertEquals(8, $this->t->count());
        $this->t->drop_table();
        $this->assertEquals(0, $this->t->count());
    }

    function testMin()
    {
        $this->assertEquals(2, $this->t->min());
        $this->t->drop_table();
        $this->assertFalse($this->t->min());
    }

    function testMax()
    {
        $this->assertEquals(19, $this->t->max());
        $this->t->drop_table();
        $this->assertFalse($this->t->max());
    }

    function testFirst()
    {
        $this->assertEquals(8, $this->t->first());
        $this->assertEquals(array('id' => 3), $this->t->each());
        $this->assertEquals(8, $this->t->first());
        $this->assertEquals(array('id' => 11), $this->t->end());
        $this->assertEquals(8, $this->t->first());
        $this->assertEquals(array('id' => 8), $this->t->reset());
        $this->assertEquals(8, $this->t->first());
        $this->t->drop_table();
        $this->assertFalse($this->t->first());
    }

    function testLast()
    {
        $this->assertEquals(11, $this->t->last());
        $this->assertEquals(array('id' => 3), $this->t->each());
        $this->assertEquals(11, $this->t->last());
        $this->assertEquals(array('id' => 11), $this->t->end());
        $this->assertEquals(11, $this->t->last());
        $this->assertEquals(array('id' => 8), $this->t->reset());
        $this->assertEquals(11, $this->t->last());
        $this->t->drop_table();
        $this->assertFalse($this->t->last());
    }

    function testPrev()
    {
        $this->assertEquals(15, $this->t->prev(3));
        $this->assertEquals(array('id' => 3), $this->t->each());
        $this->assertEquals(15, $this->t->prev(3));
        $this->assertEquals(2, $this->t->prev(3, 2));
        $this->assertEquals(8, $this->t->prev(3, 3));
        $this->assertFalse($this->t->prev(3, 5));
        $this->t->drop_table();
        $this->assertFalse($this->t->prev(3));
    }

    function testNext()
    {
        $this->assertEquals(19, $this->t->next(3));
        $this->assertEquals(array('id' => 3), $this->t->each());
        $this->assertEquals(19, $this->t->next(3));
        $this->assertEquals(9, $this->t->next(3, 2));
        $this->assertEquals(11, $this->t->next(3, 4));
        $this->assertFalse($this->t->next(3, 5));
        $this->t->drop_table();
        $this->assertFalse($this->t->next(3));
    }

    function testRand()
    {
        $r = $this->t->rand();
        $this->assertTrue(2 <= $r && $r <= 19);
        $r = $this->t->rand(2);
        $this->assertEquals(2, count($r));
        $this->assertTrue(2 <= $r[0] && $r[0] <= 19);
        $this->assertTrue(2 <= $r[1] && $r[1] <= 19);
        $r = $this->t->rand(7);
        $this->assertEquals(7, count($r));
        $this->t->drop_table();
        $this->assertFalse(@$this->t->rand());
        $this->assertFalse(@$this->t->rand(2));
        $this->assertFalse(@$this->t->rand(7));
    }

    function testSeek()
    {
        $this->assertEquals(true, $this->t->seek(5, SEEK_SET));
        $this->assertEquals(array('id' => 9), current($this->t->data));
        $this->assertEquals(true, $this->t->seek(2, SEEK_SET));
        $this->assertEquals(array('id' => 15), current($this->t->data));
        $this->assertEquals(true, $this->t->seek(1, SEEK_CUR));
        $this->assertEquals(array('id' => 3), current($this->t->data));
        $this->assertEquals(true, $this->t->seek(3, SEEK_CUR));
        $this->assertEquals(array('id' => 4), current($this->t->data));
        $this->assertEquals(false, $this->t->seek(2, SEEK_CUR));
        $this->assertEquals(false, current($this->t->data));
        $this->assertEquals(true, $this->t->seek(5, SEEK_END));
        $this->assertEquals(array('id' => 15), current($this->t->data));
        $this->assertEquals(true, $this->t->seek(9));
        $this->assertEquals(array('id' => 9), current($this->t->data));
        $this->assertEquals(true, $this->t->seek(2));
        $this->assertEquals(array('id' => 2), current($this->t->data));
    }

    function testData_seek()
    {
        $this->assertEquals(true, $this->t->data_seek(5));
        $this->assertEquals(array('id' => 9), $this->t->each());
        $this->assertEquals(true, $this->t->data_seek(2));
        $this->assertEquals(array('id' => 15), $this->t->each());
    }

    function testLimit()
    {
        $this->t->limit(2);
        $this->assertEquals(array('id' => 8), $this->t->each());
        $this->assertEquals(array('id' => 2), $this->t->each());
        $this->assertEquals(false, $this->t->each());
        $this->assertEquals(true, $this->t->limit(2, 9));
        $this->assertEquals(array('id' => 9), $this->t->each());
        $this->assertEquals(array('id' => 4), $this->t->each());
        $this->assertEquals(false, $this->t->each());
        $this->assertEquals(true, $this->t->limit(2, 11));
        $this->assertEquals(array('id' => 11), $this->t->each());
        $this->assertEquals(false, $this->t->each());
    }

    function testAdd_field()
    {
        $this->assertTrue($this->tMixed->add_field("neu"));
        $this->assertEquals(array("id", "a", "b", "c", "neu"), $this->tMixed->fields);
        $this->assertTrue($this->tMixed->add_field("mitte", "a"));
        $this->assertEquals(array("id", "a", "mitte", "b", "c", "neu"), $this->tMixed->fields);
        $this->assertFalse($this->tMixed->add_field("b"));
        $this->assertEquals(array("id", "a", "mitte", "b", "c", "neu"), $this->tMixed->fields);
    }

    function testDrop_field()
    {
        $this->assertEquals(4, count($this->tMixed->fields));
        $this->assertEquals(5, $this->tMixed->count());
        $this->assertFalse($this->tMixed->drop_field("id"));
        $this->assertTrue($this->tMixed->drop_field("b"));
        $this->assertEquals(3, count($this->tMixed->fields));
        $this->assertEquals(array('id' => 1, 'a' => "A1", 'c' => 12), $this->tMixed->fetch_assoc());
        $this->assertFalse($this->tMixed->drop_field("unknown"));
        $this->assertEquals(3, count($this->tMixed->fields));
        $this->assertEquals(array('id' => 2, 'a' => "A2", 'c' => "12"), $this->tMixed->fetch_assoc());
        $this->assertFalse($this->tMixed->drop_field(array("crap")));
    }

    function testDrop_table()
    {
        $this->assertEquals(4, count($this->tMixed->fields));
        $this->assertEquals(5, $this->tMixed->count());
        $this->tMixed->drop_table();
        $this->assertEquals(1, count($this->tMixed->fields));
        $this->assertEquals(array("id"), $this->tMixed->fields);
        $this->assertEquals(0, $this->tMixed->count());
    }

    function testInsert()
    {
        $this->assertEquals(5, $this->tMixed->count());
        $this->tMixed->insert(array('a' => 21));
        $this->tMixed->insert(array('id' => 88,'a' => 22));
        $this->tMixed->insert(array('id' => 1,'a' => 23));
        $a = $this->tMixed->each();
        $this->assertEquals(1, $a['id']);
        $a = $this->tMixed->each();
        $this->assertEquals(2, $a['id']);
        $a = $this->tMixed->each();
        $this->assertEquals(3, $a['id']);
        $a = $this->tMixed->each();
        $this->assertEquals(5, $a['id']);
        $a = $this->tMixed->each();
        $this->assertEquals("x", $a['id']);
        $this->assertEquals(array('id' => 6, 'a' => 21), $this->tMixed->each());
        $this->assertEquals(array('id' => 88, 'a' => 22), $this->tMixed->each());
        $this->assertEquals(array('id' => 89, 'a' => 23), $this->tMixed->each());
        $this->tMixed->fields = array();
        $this->tMixed->insert(array('a' => "a", 'b' => "b", 'id' => 30, 'c' => "c"));
        $this->assertEquals(array("id", "a", "b", "c"), $this->tMixed->fields);
        $this->assertEquals(array('id' => 30, 'a' => "a", 'b' => "b", 'c' => "c"), $this->tMixed->each());
    }

    function testInsert_id()
    {
        $this->assertEquals("x", $this->tMixed->insert_id());
        $this->tMixed->insert(array('a' => 1));
        $this->assertEquals(6, $this->tMixed->insert_id());
        $this->tMixed->insert(array('id' => 13, 'a' => 1));
        $this->assertEquals(13, $this->tMixed->insert_id());
        $this->tMixed->insert(array('a' => 1));
        $this->assertEquals(14, $this->tMixed->insert_id());
        $this->tMixed->insert(array('id' => 13, 'a' => 1));
        $this->assertEquals(15, $this->tMixed->insert_id());
        $this->tMixed->drop_table();
        $this->tMixed->insert(array('a' => 1));
        $this->assertEquals(1, $this->tMixed->insert_id());
    }

    function testAuto_increment()
    {
        $t = new MyCSV();
        $this->assertFalse($t->insert_id());
        $this->assertTrue(false === $t->insert_id());
        $t->insert(array('a' => "a"));
        $this->assertEquals(1, $t->insert_id());
        $t->insert(array('id' => 12, 'a' => "a"));
        $this->assertEquals(12, $t->insert_id());
        $t->insert_id = 7;
        $t->insert(array('a' => "a"));
        $this->assertEquals(7, $t->insert_id());
        $t->insert(array('a' => "a"));
        $this->assertEquals(8, $t->insert_id());
        $t->insert(array('id' => "x3", 'a' => "a"));
        $this->assertEquals("x3", $t->insert_id());
        $t->insert_id = "x25";
        $t->insert(array('a' => "a"));
        $this->assertEquals("x25", $t->insert_id());
    }

    function testDelete()
    {
        $this->assertEquals(5, $this->tMixed->count());
        $this->tMixed->delete(2);
        $this->assertFalse($this->tMixed->id_exists(2));
        $this->assertEquals(4, $this->tMixed->count());
        $this->tMixed->delete(19);
        $this->assertEquals(4, $this->tMixed->count());
        $this->assertTrue($this->tMixed->id_exists("x"));
        $this->tMixed->delete(array('id' => "x"));
        $this->assertFalse($this->tMixed->id_exists("x"));
        $this->assertEquals(3, $this->tMixed->count());
        $this->tMixed->delete(array(5));
        $this->assertEquals(3, $this->tMixed->count());
        $this->tMixed->delete("");
        $this->assertEquals(3, $this->tMixed->count());
        $this->tMixed->delete();
        $this->assertEquals(0, $this->tMixed->count());
    }

    function testSort()
    {
        $this->tMixed->sort("b DESC");
        $this->assertEquals("id,a,b,c\r\n" .
            "3,\"A3\",\"42\",\"111\"\r\n" .
            "1,\"A1\",\"38\",\"12\"\r\n" .
            "x,\"Ax\",\"31\",\"11\"\r\n" .
            "2,\"A2\",\"27\",\"12\"\r\n" .
            "5,\"A5\",\"13\",\"15\"\r\n", $this->tMixed->export());
        $this->tMixed->sort("b", SORT_ASC);
        $this->tMixed->sort("a,c,b");
        $this->tMixed->sort("c,SORT_STRING,SORT_NULL,a,b ASC");
        $this->assertEquals("id,a,b,c\r\n" .
            "x,\"Ax\",\"31\",\"11\"\r\n" .
            "3,\"A3\",\"42\",\"111\"\r\n" .
            "1,\"A1\",\"38\",\"12\"\r\n" .
            "2,\"A2\",\"27\",\"12\"\r\n" .
            "5,\"A5\",\"13\",\"15\"\r\n", $this->tMixed->export());
    }

    function testSortNumeric()
    {
        $a = array("a" => 100);
        $b = array("a" => 101);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_NUMERIC, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);

        $a = array("a" => 10000);
        $b = array("a" => -10000);
        $this->assertTrue($this->t->_cmp($a, $b) > 0);

        $a = array("a" => "100x");
        $b = array("a" => "100y");
        $this->assertTrue($this->t->_cmp($a, $b) == 0);

        $a = array("a" => 100);
        $b = array("a" => "100y");
        $this->assertTrue($this->t->_cmp($a, $b) == 0);

        $a = array("a" => "10");
        $b = array("a" => "2");
        $this->assertTrue($this->t->_cmp($a, $b) > 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_STRING, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);

        $a = array("a" => 1.00);
        $b = array("a" => 1.01);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_NUMERIC, 'order' => -1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);
    }

    function testSortString()
    {
        $a = array("a" => "z");
        $b = array("a" => "a");
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_STRING, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);

        $a = array("a" => "90a");
        $b = array("a" => "90b");
        $this->assertTrue($this->t->_cmp($a, $b) < 0);

        // Isn't PHP strange?
        $a = array("a" => 9000);
        $b = array("a" => "9000b");
        $this->assertTrue($this->t->_cmp($a, $b) < 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_NUMERIC, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) == 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => 0, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) == 0);

        $a = array("a" => "z");
        $b = array("a" => "aaaaaa");
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_STRING, 'order' => -1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);
    }

    function testSortLocaleString()
    {
        setlocale(LC_ALL, "de_DE@euro", "de_DE", "deu_deu");
        $a = array("a" => "ä");
        $b = array("a" => "b");
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_STRING, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_LOCALE_STRING, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);

        $a = array("a" => "Ä");
        $b = array("a" => "ä");
        $this->assertTrue($this->t->_cmp($a, $b) == 0);
    }

    function testSortNatural()
    {
        $a = array("a" => "x10");
        $b = array("a" => "x2");
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_NAT, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_STRING, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => 0, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);
    }

    function testSortTime()
    {
        $a = array("a" => "March 1st 1980");
        $b = array("a" => "December 1st 1980");
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_TIME, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => 0, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);
    }

    function testSortNull()
    {
        $a = array("a" => "");
        $b = array("a" => "x");
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_NULL, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_NULL, 'order' => -1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);

        $a = array("a" => "0");
        $b = array("a" => "!");
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_STRING | SORT_NULL, 'order' => 1));
        $this->assertTrue($this->t->_cmp($a, $b) > 0);
        $this->t->_cmpFields = array(array('field' => 'a', 'type' => SORT_STRING | SORT_NULL, 'order' => -1));
        $this->assertTrue($this->t->_cmp($a, $b) < 0);
    }

    function testJoin()
    {
        $rightTable = new MyCSV();
        $rightTable->insert(array('id' => 27, 'extra' => "X1"));
        $rightTable->insert(array('id' => 42, 'extra' => "X2"));
        $this->tMixed->join($rightTable, "b");
        $this->assertEquals(5, $this->tMixed->count());
        $a = $this->tMixed->each();
        $this->assertFalse(isset($a['extra']));
        $a = $this->tMixed->each();
        $this->assertEquals("X1", $a['extra']);
        $a = $this->tMixed->each();
        $this->assertEquals("X2", $a['extra']);
        $a = $this->tMixed->each();
        $this->assertFalse(isset($a['extra']));
        $a = $this->tMixed->each();
        $this->assertFalse(isset($a['extra']));
        $rightTable = new MyCSV("dummy/path/rigHt.CSv");
        $rightTable->insert(array('id' => 31, 'c' => "Yyy1"));
        $rightTable->insert(array('id' => 42, 'c' => "Yyy2", 'k9' => "k9"));
        $this->tMixed->join($rightTable, "b");
        $a = $this->tMixed->each();
        $this->assertFalse(isset($a['rigHt.c']));
        $a = $this->tMixed->each();
        $this->assertFalse(isset($a['rigHt.c']));
        $a = $this->tMixed->each();
        $this->assertEquals("Yyy2", $a['rigHt.c']);
        $this->tMixed->data[3]['k9'] = "k22";
        $this->assertEquals("k22", $this->tMixed->data[3]['rigHt.k9']);
    }

    function testExport()
    {
        $this->tMixed->drop_table();
        $this->assertEquals("id\r\n", $this->tMixed->export());
        $this->tMixed->insert(array('a' => 'Me "q" & \'d\\/b\''));
        $this->tMixed->insert(array('a' => "'xtr\a \"lrg\" e'\\"));
        $this->tMixed->insert(array('a' => "a", 'b' => "b"));
        $this->assertEquals("id,a\r\n" .
            "1,\"Me \"\"q\"\" & 'd\/b'\"\r\n" .
            "2,\"'xtr\a \"\"lrg\"\" e'\\\\\"\r\n" .
            "3,\"a\"\r\n", $this->tMixed->export());
        $this->tMixed->delete();
        $this->tMixed->add_field("b");
        $this->tMixed->insert(array('b' => "b"));
        $this->assertEquals("id,a,b\r\n" .
            "4,,\"b\"\r\n", $this->tMixed->export());
    }
}

$suite = new PHPUnit_TestSuite("MyCSVTest");
// $result = PHPUnit::run($suite);
// echo $result->toHTML();
$gui = new PHPUnit_GUI_HTML($suite);
$gui->show();
