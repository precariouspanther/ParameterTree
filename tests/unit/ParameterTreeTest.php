<?php
namespace tests\unit;

use Arcanum\ParameterTree\ParameterTree;
use PHPUnit_Framework_TestCase;


/**
 *
 * @author Adam Benson <adam@precariouspanther.net>
 * @copyright Arcanum Logic
 * @coversDefaultClass Arcanum\ParameterTree\ParameterTree
 */
class ParameterTreeTest extends PHPUnit_Framework_TestCase
{

    public function testCreateFromArray()
    {
        $tree = new ParameterTree($this->getDummyArray());

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

                "a"    => ["b" => ["c" => 230, "c2" => "string"]],
                "a2"   => ["b" => ["c" => 220]],
                "test" => [1 => 4, 2 => 30]
            ],
            $tree->toArray()
        );

        $tree->delete("a.b.c");
        $this->assertEquals(
            [

                "a"    => ["b" => ["c2" => "string"]],
                "a2"   => ["b" => ["c" => 220]],
                "test" => [1 => 4, 2 => 30]
            ],
            $tree->toArray()
        );

        $tree->delete("test");
        $this->assertEquals(
            [

                "a"  => ["b" => ["c2" => "string"]],
                "a2" => ["b" => ["c" => 220]]
            ],
            $tree->toArray()
        );

        $tree->delete("a.b");
        $this->assertEquals(
            [

                "a"  => [],
                "a2" => ["b" => ["c" => 220]]
            ],
            $tree->toArray()
        );
    }

    public function testCount()
    {
        $tree = new ParameterTree($this->getDummyArray());
        $this->assertEquals(7, $tree->count());
    }

    public function testFind()
    {
        $tree = new ParameterTree($this->getDummyArray());

        $this->assertEquals("key2.branch.subsub1", $tree->find("Test"));
        $this->assertNull($tree->find("TestDoesntExit"));
    }

    public function testArrayAccess()
    {
        $tree = new ParameterTree($this->getDummyArray());

        $this->assertNull($tree['bad.array.key']);
        $this->assertEquals(false, $tree['test1']);
        $this->assertEquals(45, $tree['key2.subkey1']);

        $tree['test2.test'] = "fish";

        $this->assertTrue(isset($tree['test2.test']));
        $this->assertEquals("fish", $tree->get("test2.test"));

        unset($tree['test2.test']);
        $this->assertNull($tree->get("test2.test"));

    }

    /**
     * @covers ::getBranch
     */
    public function testGetBranch()
    {
        $tree = new ParameterTree([
            "test1"  => false,
            "nulled" => null,
            "key2"   => [
                "subkey1" => 45,
                "subkey4" => 33,
                "branch"  => [
                    "subsub1" => "Test",
                    1         => "Numeric"
                ]
            ]
        ]);

        $branch = $tree->getBranch("key2");
        $subbranch = $tree->getBranch("key2.branch");

        $this->assertInstanceOf(ParameterTree::class, $branch);
        $this->assertEquals(45, $branch->get("subkey1"));
        $this->assertEquals('Test', $branch->get("branch.subsub1"));

        $this->assertInstanceOf(ParameterTree::class, $subbranch);
        $this->assertEquals("Test", $subbranch->get("subsub1"));
        $this->assertNull($tree->getBranch("nulled"),
            "nulled key should return null if set to null instead of a null branch");
    }

    /**
     * @expectedException \Arcanum\ParameterTree\Exception\MissingValueException
     */
    public function testGetBranchScalar()
    {
        $tree = new ParameterTree($this->getDummyArray());
        $tree->getBranch("test1");
    }

    public function testBroken(){
        $this->assertTrue(false);
    }

    /**
     * @expectedException \Arcanum\ParameterTree\Exception\MissingValueException
     */
    public function testGetBranchMissingSubbranch()
    {
        $tree = new ParameterTree($this->getDummyArray());
        $tree->getBranch("test1.test2");
    }

    /**
     * @expectedException \Arcanum\ParameterTree\Exception\MissingValueException
     */
    public function testGetBranchMissing()
    {
        $tree = new ParameterTree($this->getDummyArray());
        $tree->getBranch("missing");
    }

    public function testTypecast()
    {
        $tree = new ParameterTree();
        $tree->set("branch.val1", 0);
        $tree->set("branch.val2", "true");
        $tree->set("branch.val3", "5 apples");
        $tree->set("branch.val4", false);
        $tree->set("branch.val5", '!!!²blah');

        $this->assertFalse($tree->getBoolean("branch.val1"));
        $this->assertTrue($tree->getBoolean("branch.val2"));
        $this->assertTrue($tree->getBoolean("branch.val3"));
        $this->assertFalse($tree->getBoolean("branch.val4"));

        $this->assertEquals(5, $tree->getInt("branch.val3"));

        $this->assertEquals("0", $tree->getString("branch.val1"));
        $this->assertEquals("true", $tree->getString("branch.val2"));
        $this->assertEquals("5 apples", $tree->getString("branch.val3"));
        $this->assertEquals("", $tree->getString("branch.val4"));
        $this->assertEquals('!!!²blah', $tree->getString("branch.val5"));

    }

    public function testFalseyKey()
    {
        $tree = new ParameterTree([
            0        => [
                "abc" => 123
            ],
            "fruits" => [
                ["name" => "Apple", "colour" => "green"],
                ["name" => "Orange", "colour" => "Orange"],
                ["name" => "Banana", "colour" => "Yellow"],
            ]
        ]);

        $appleBranch = $tree->getBranch("fruits.0");

        $this->assertInstanceOf(ParameterTree::class, $appleBranch);
        $this->assertEquals("Apple", $appleBranch->get("name"));
        $this->assertEquals("Apple", $tree->get("fruits.0.name"));
        $this->assertEquals("Orange", $tree->get("fruits.1.name"));
        $this->assertEquals("fruits.0",$appleBranch->getPath());
        $this->assertTrue($tree->hasKey("fruits.0"));
        $this->assertFalse($tree->hasKey("fruits.8"));
        $this->assertTrue($appleBranch->hasKey("name"));
        $this->assertFalse($appleBranch->hasKey("sweetness"));

    }


    /**
     * @covers ::getString
     */
    public function testGetString()
    {
        $tree = new ParameterTree();
        $tree->set("branch.val1", 0);
        $tree->set("branch.val2", "true");
        $tree->set("branch.val3", "5 apples");
        $tree->set("branch.val4", false);
        $tree->set("branch.val5", '!!!²blah');

        $this->assertEquals("0", $tree->getString("branch.val1"));
        $this->assertEquals("true", $tree->getString("branch.val2"));
        $this->assertEquals("5 apples", $tree->getString("branch.val3"));
        $this->assertEquals("", $tree->getString("branch.val4"));
        $this->assertEquals('!!!²blah', $tree->getString("branch.val5"));
    }

    /**
     * @covers ::toArray
     */
    public function testToArray()
    {
        $tree = new ParameterTree();
        for ($x = 0; $x < 3; $x++) {
            for ($y = 0; $y < 3; $y++) {
                $tree->set("$x.$y", $x * $y);
            }
        }

        $this->assertEquals([
            0 => [0 => 0, 1 => 0, 2 => 0],
            1 => [0 => 0, 1 => 1, 2 => 2],
            2 => [0 => 0, 1 => 2, 2 => 4],
        ], $tree->toArray());
    }

    /**
     * @covers ::jsonSerialize
     */
    public function testJsonSerialize()
    {
        /* Arrange */
        $tree = new ParameterTree();
        for ($x = 0; $x < 3; $x++) {
            for ($y = 0; $y < 3; $y++) {
                $tree->set("$x.$y", $x * $y);
            }
        }
        /* Act */
        $result = json_encode($tree);
        /* Assert */
        $this->assertEquals("[[0,0,0],[0,1,2],[0,2,4]]", $result);
    }

    /**
     * @expectedException \Exception
     * @throws \Exception
     * @covers ::getString
     */
    public function testInvalidString()
    {
        $tree = new ParameterTree();
        $tree->set("trunk.branch.value", 123);
        $tree->getString("trunk");
    }

    /**
     * @covers ::getKeys
     */
    public function testGetKeys()
    {
        $tree = new ParameterTree($this->getDummyArray());

        $this->assertEquals(
            ["test1", "key2.subkey1", "key2.subkey4", "key2.branch.subsub1", "key2.branch.1"],
            $tree->getKeys()
        );
    }

    /**
     * @throws \Arcanum\ParameterTree\Exception\ValueExistsException
     * @expectedException \Arcanum\ParameterTree\Exception\ValueExistsException
     * @covers ::set
     */
    public function testBranchProtection()
    {
        $tree = new ParameterTree(["chicken" => 1]);
        $tree->set("chicken", 321);
    }

    /**
     * @covers ::set
     */
    public function testBranchProtectionForce()
    {
        $tree = new ParameterTree(["chicken" => 1]);
        $tree->set("chicken", 321, true);

        $this->assertEquals(321, $tree->get("chicken"));
    }

    /**
     * @return array
     */
    private function getDummyArray()
    {
        return [
            "test1" => false,
            "key2"  => [
                "subkey1" => 45,
                "subkey4" => 33,
                "branch"  => [
                    "subsub1" => "Test",
                    1         => "Numeric"
                ]
            ]
        ];
    }
}
