<?php

/**
 *
 * @author Adam Benson <adam@precariouspanther.net>
 * @copyright Arcanum Logic
 */
class ParameterTreeTest extends PHPUnit_Framework_TestCase
{
    public function testCreateFromArray()
    {
        $tree = \Arcanum\ParameterTree\ParameterTree::CreateFromArray($this->getDummyArray());

        //Existing keys
        $this->assertTrue($tree->hasKey("test1"));
        $this->assertTrue($tree->hasKey("key2.subkey1"));
        $this->assertTrue($tree->hasKey("key2.subkey4"));
        $this->assertTrue($tree->hasKey("key2.branch"));
        $this->assertTrue($tree->hasKey("key2.branch.subsub1"));
        $this->assertTrue($tree->hasKey("key2.branch.1"));
        //Non-existing keys
        $this->assertFalse($tree->hasKey("blank"));
        $this->assertFalse($tree->hasKey("blank.multi.long.subkey"));
        $this->assertFalse($tree->hasKey("key2.subkey6"));
        $this->assertFalse($tree->hasKey("key2.1"));

        //Value fetches
        $this->assertEquals(45, $tree->get("key2.subkey1"));
        $this->assertEquals(33, $tree->get("key2.subkey4"));
        $this->assertEquals("Test", $tree->get("key2.branch.subsub1"));

        //Default value returns
        $this->assertEquals(123, $tree->get("key2.missingKey", 123));
        $this->assertEquals(123, $tree->get("key2.subkey1.invalidsubkey", 123));
        $this->assertEquals(123, $tree->get("key55", 123));
        $this->assertEquals("string", $tree->get("test5", "string"));
        $this->assertNull($tree->get("test5"));

        //toArray should return exactly what we provided
        $this->assertEquals($this->getDummyArray(), $tree->toArray());
    }

    public function testChange()
    {
        $tree = new \Arcanum\ParameterTree\ParameterTree();

        $tree->set("a.b.c", 230);
        $tree->set("a.b.c2", "string");
        $tree->set("a2.b.c", 220);
        $tree->set("test", [1 => 4, 2 => 30]);

        $this->assertEquals(
            [

                "a" => ["b" => ["c" => 230, "c2" => "string"]],
                "a2" => ["b" => ["c" => 220]],
                "test" => [1 => 4, 2 => 30]
            ],
            $tree->toArray()
        );

        $tree->delete("a.b.c");
        $this->assertEquals(
            [

                "a" => ["b" => ["c2" => "string"]],
                "a2" => ["b" => ["c" => 220]],
                "test" => [1 => 4, 2 => 30]
            ],
            $tree->toArray()
        );

        $tree->delete("test");
        $this->assertEquals(
            [

                "a" => ["b" => ["c2" => "string"]],
                "a2" => ["b" => ["c" => 220]]
            ],
            $tree->toArray()
        );

        $tree->delete("a.b");
        $this->assertEquals(
            [

                "a" => [],
                "a2" => ["b" => ["c" => 220]]
            ],
            $tree->toArray()
        );
    }

    public function testCount()
    {
        $tree = \Arcanum\ParameterTree\ParameterTree::CreateFromArray($this->getDummyArray());
        $this->assertEquals(7, $tree->count());
    }

    public function testFind()
    {
        $tree = \Arcanum\ParameterTree\ParameterTree::CreateFromArray($this->getDummyArray());

        $this->assertEquals("key2.branch.subsub1", $tree->find("Test"));
        $this->assertNull($tree->find("TestDoesntExit"));
    }

    public function testArrayAccess()
    {
        $tree = \Arcanum\ParameterTree\ParameterTree::CreateFromArray($this->getDummyArray());

        $this->assertNull($tree['bad.array.key']);
        $this->assertEquals(false, $tree['test1']);
        $this->assertEquals(45, $tree['key2.subkey1']);

        $tree['test2.test'] = "fish";

        $this->assertTrue(isset($tree['test2.test']));
        $this->assertEquals("fish", $tree->get("test2.test"));

        unset($tree['test2.test']);
        $this->assertNull($tree->get("test2.test"));

    }

    public function testTypecast()
    {
        $tree = new \Arcanum\ParameterTree\ParameterTree();
        $tree->set("branch.val1", 0);
        $tree->set("branch.val2", "true");
        $tree->set("branch.val3", "5 apples");
        $tree->set("branch.val4", false);
        $tree->set("branch.val5", '!!!²blah');

        $this->assertFalse($tree->getBoolean("branch.val1"));
        $this->assertTrue($tree->getBoolean("branch.val2"));
        $this->assertTrue($tree->getBoolean("branch.val3"));
        $this->assertFalse($tree->getBoolean("branch.val4"));

        $this->assertEquals(5,$tree->getInt("branch.val3"));

        $this->assertEquals("0",$tree->getString("branch.val1"));
        $this->assertEquals("true",$tree->getString("branch.val2"));
        $this->assertEquals("5 apples",$tree->getString("branch.val3"));
        $this->assertEquals("",$tree->getString("branch.val4"));
        $this->assertEquals('!!!²blah',$tree->getString("branch.val5"));

    }

    /**
     * @expectedException Exception
     * @throws Exception
     */
    public function testInvalidString(){
        $tree = new \Arcanum\ParameterTree\ParameterTree();
        $tree->set("trunk.branch.value",123);
        $tree->getString("trunk");
    }

    public function testGetKeys()
    {
        $tree = \Arcanum\ParameterTree\ParameterTree::CreateFromArray($this->getDummyArray());

        $this->assertEquals(
            ["test1", "key2.subkey1", "key2.subkey4", "key2.branch.subsub1", "key2.branch.1"],
            $tree->getKeys()
        );
    }

    /**
     * @throws Exception
     * @expectedException Exception
     */
    public function testBranchProtection()
    {
        $tree = \Arcanum\ParameterTree\ParameterTree::CreateFromArray($this->getDummyArray());
        $tree->set("key2.branch", 321);
    }

    public
    function testBranchProtectionForce()
    {
        $tree = \Arcanum\ParameterTree\ParameterTree::CreateFromArray($this->getDummyArray());
        $tree->set("key2.branch", 321, true);

        $this->assertEquals(321, $tree->get("key2.branch"));
    }

    /**
     * @return array
     */
    private
    function getDummyArray()
    {
        return [
            "test1" => false,
            "key2" => [
                "subkey1" => 45,
                "subkey4" => 33,
                "branch" => [
                    "subsub1" => "Test",
                    1 => "Numeric"
                ]
            ]
        ];
    }
}
