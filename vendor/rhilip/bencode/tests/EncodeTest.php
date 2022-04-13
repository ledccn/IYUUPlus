<?php

use Rhilip\Bencode\Bencode;
use PHPUnit\Framework\TestCase;

class EncodeTest extends TestCase
{
    /**
     * @group integer
     */
    public function testEncodeNumber()
    {
        // positive
        $this->assertEquals('i314e', Bencode::encode(314));

        // negative
        $this->assertEquals('i-512e', Bencode::encode(-512));

        // zero
        $this->assertEquals('i0e', Bencode::encode(0));
        $this->assertEquals('i0e', Bencode::encode(-0));
    }

    /**
     * @group string
     */
    public function testEncodeString()
    {
        // arbitrary
        $this->assertEquals('11:test string', Bencode::encode('test string'));

        // special characters
        $this->assertEquals("25:zero\0newline\nsymblol05\x05ok", Bencode::encode("zero\0newline\nsymblol05\x05ok"));

        // empty
        $this->assertEquals('0:', Bencode::encode(''));

        // unicode. prefix number reflects the number if bytes
        $this->assertEquals('9:日本語', Bencode::encode('日本語'));

        // scalars converted to string
        $this->assertEquals('6:3.1416', Bencode::encode(3.1416));

        // number in string type
        $this->assertEquals('12:123456789012', Bencode::encode('123456789012'));
    }

    /**
     * Boolean will convert silently to string since boolean and NULL is not valid Bencode type,
     * mapped as :
     *  - true  -> "1"
     *  - false -> ""  (like empty string)
     *  - null  -> ""  (like empty string)
     *
     * @group string
     */
    public function testEncodeBooleanAndNull()
    {
        $this->assertEquals('1:1', Bencode::encode(true));
        $this->assertEquals('0:', Bencode::encode(false));
        $this->assertEquals('0:', Bencode::encode(null));
    }

    /**
     * @group list
     */
    public function testEncodeList()
    {
        // sequential array should become list
        $this->assertEquals('li1ei2e1:34:testi5ee', Bencode::encode([1, 2, '3', 'test', 5]));

        // empty list
        $this->assertEquals('le', Bencode::encode([]));
    }

    /**
     * @group dictionary
     */
    public function testEncodeDictionary()
    {
        // array with string keys
        $this->assertEquals('d3:key5:value4:test8:whatevere', Bencode::encode(['key' => 'value', 'test' => 'whatever']));

        // any non-sequential array
        $this->assertEquals('d1:0i1e1:1i2e1:21:31:3i5e1:44:teste', Bencode::encode([1, 2, '3', 4 => 'test', 3 => 5]));

        // keys should be sorted by binary comparison of the strings
        $stringKeys = [
            'a'     => '',
            'b'     => '',
            'c'     => '',
            'A'     => '',
            'B'     => '',
            'C'     => '',
            'key'   => '',
            '本'     => '',
            'ы'     => '',
            'Ы'     => '',
            'š'     => '',
            'Š'     => '',
        ];
        $expectedWithStringKeys = 'd' .
            '1:A0:' .
            '1:B0:' .
            '1:C0:' .
            '1:a0:' .
            '1:b0:' .
            '1:c0:' .
            '3:key0:' .
            '2:Š0:' .
            '2:š0:' .
            '2:Ы0:' .
            '2:ы0:' .
            '3:本0:' .
            'e';

        $this->assertEquals($expectedWithStringKeys, Bencode::encode($stringKeys));

        // also check that php doesn't silently convert numeric keys to integer
        $numericKeys = [
            1 => '',
            5 => '',
            9 => '',
            11 => '',
            55 => '',
            99 => '',
            111 => '',
            555 => '',
            999 => '',
        ];

        $expectedWithNumericKeys = 'd' .
            '1:10:' .
            '2:110:' .
            '3:1110:' .
            '1:50:' .
            '2:550:' .
            '3:5550:' .
            '1:90:' .
            '2:990:' .
            '3:9990:' .
            'e';

        $this->assertEquals($expectedWithNumericKeys, Bencode::encode($numericKeys));
    }

    /**
     * @group all
     */
    public function testEncodeAllTypes()
    {
        // just so some data in combinations
        $data1 = [
            'integer'   => 1,           // 7:integeri1e
            'list'      => [
                1, 2, 3, 'test',
                ['list', 'in', 'list'], // l4:list2:in4:liste
                ['dict' => 'in list'],  // d4:dict7:in liste
            ],                          // 4:listli1ei2ei3e4:testl4:list2:in4:listed4:dict7:in listee
            'dict'      => [
                'int' => 123, 'list' => []
            ],                          // 4:dictd3:inti123e4:listlee
            'string'    => 'str',       // 6:string3:str
        ];
        $data2 = [
            'integer'   => 1,
            'string'    => 'str',
            'dict'      => ['list' => [], 'int' => 123],
            'list'      => [1, 2, 3, 'test', ['list', 'in', 'list'], ['dict' => 'in list']],
        ];

        $expected = 'd4:dictd3:inti123e4:listlee7:integeri1e4:listli1ei2ei3e4:testl4:list2:in4:listed4:dict7:in listee6:string3:stre';

        $result1 = Bencode::encode($data1);
        $result2 = Bencode::encode($data2);

        $this->assertEquals($expected, $result1);
        $this->assertEquals($result1, $result2); // different order of dict keys should not change the result
    }
}
