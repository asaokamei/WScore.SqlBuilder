<?php

namespace tests\Sql;

use PHPUnit\Framework\TestCase;
use WScore\ScoreSql\Builder\Quote;

require_once(dirname(__DIR__) . '/autoloader.php');

class Quote_Test extends TestCase
{
    /**
     * @var Quote
     */
    var $q;

    function setup(): void
    {
        $this->q = new Quote();
    }

    function test0()
    {
        $this->assertEquals('WScore\ScoreSql\Builder\Quote', get_class($this->q));
    }

    /**
     * @test
     */
    function quote_wraps_value()
    {
        $token = $this->get();
        $quoted = $this->q->quote($token);
        $this->assertEquals("\"{$token}\"", $quoted);
    }

    function get($head = 'test')
    {
        return $head . mt_rand(1000, 9999);
    }

    /**
     * @test
     */
    function setQuote_uses_different_char_to_quote()
    {
        $token = $this->get();
        $this->q->setQuote('*');
        $quoted = $this->q->quote($token);
        $this->assertEquals("*{$token}*", $quoted);
    }

    /**
     * @test
     */
    function quote_does_not_quote_a_quoted_value()
    {
        $token = $this->q->quote($this->get());
        $quoted = $this->q->quote($token);
        $this->assertEquals($token, $quoted);
    }

    /**
     * @test
     */
    function quote_split_as_and_space_and_period()
    {
        $quoted = $this->q->quote("test more");
        $this->assertEquals('"test more"', $quoted);

        $quoted = $this->q->quote("\"test more\".col");
        $this->assertEquals('"test more"."col"', $quoted);

        $quoted = $this->q->quote("test.more as quote");
        $this->assertEquals('"test"."more" as "quote"', $quoted);

        $quoted = $this->q->quote("test.more AS quote");
        $this->assertEquals('"test"."more" AS "quote"', $quoted);
    }

    /**
     * @test
     */
    function quote_with_prefix_and_parent()
    {
        $quoted = $this->q->quote('$.test', 'sub', 'main');
        $this->assertEquals('"main"."test"', $quoted);
    }
}
