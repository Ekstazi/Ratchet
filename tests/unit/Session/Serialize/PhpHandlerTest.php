<?php

namespace tests\Session\Serialize;

use PHPUnit\Framework\TestCase;
use Reamp\Session\Serialize\PhpHandler;

/**
 * @covers \Reamp\Session\Serialize\PhpHandler
 */
class PhpHandlerTest extends TestCase {
    protected $_handler;

    public function setUp() {
        $this->_handler = new PhpHandler();
    }

    public function serializedProvider() {
        return [
            [
                '_sf2_attributes|a:2:{s:5:"hello";s:5:"world";s:4:"last";i:1332872102;}_sf2_flashes|a:0:{}', [
                    '_sf2_attributes' => [
                        'hello' => 'world', 'last'  => 1332872102
                    ], '_sf2_flashes' => []
                ]
            ]
        ];
    }

    /**
     * @dataProvider serializedProvider
     */
    public function testUnserialize($in, $expected) {
        $this->assertEquals($expected, $this->_handler->unserialize($in));
    }

    /**
     * @dataProvider serializedProvider
     */
    public function testSerialize($serialized, $original) {
        $this->assertEquals($serialized, $this->_handler->serialize($original));
    }
}
