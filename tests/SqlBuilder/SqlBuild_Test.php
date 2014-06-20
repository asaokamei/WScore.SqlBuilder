<?php
namespace tests\Sql;

use WScore\SqlBuilder\Builder\Bind;
use WScore\SqlBuilder\Builder\Builder;
use WScore\SqlBuilder\Sql\Sql;
use WScore\SqlBuilder\Builder\Quote;
use WScore\SqlBuilder\Sql\Where;

require_once( dirname( __DIR__ ) . '/autoloader.php' );

class SqlBuild_Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Builder
     */
    var $builder;

    /**
     * @var \WScore\SqlBuilder\Sql\Sql
     */
    var $query;
    
    function setup()
    {
        $this->builder = new Builder( new Quote(), new Bind() );
        $this->query   = new Sql();
        Bind::reset();
    }
    
    function get($head='value') {
        return $head . mt_rand(1000,9999);
    }
    
    function test0()
    {
        $this->assertEquals( 'WScore\SqlBuilder\Builder\Builder', get_class( $this->builder ) );
        $this->assertEquals( 'WScore\SqlBuilder\Sql\Sql', get_class( $this->query ) );
    }

    /**
     * @test
     */
    function insert()
    {
        $value = $this->get();
        $this->query->table( 'testTable' )->value( 'testCol', $value );
        $sql = $this->builder->toInsert( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals( 'INSERT INTO "testTable" ( "testCol" ) VALUES ( :db_prep_1 )', $sql );
        $this->assertEquals( $value, $bind[':db_prep_1'] );
        $this->assertEquals( 1, count( $bind ) );
    }

    /**
     * @test
     */
    function update()
    {
        $values = [
            'testCol' => $this->get(),
            'moreCol' => $this->get(),
        ];
        $keyVal = $this->get();
        $this->query->table( 'testTable' )->value( $values )->where(
            Where::column('pKey')->eq($keyVal)
        );
        $sql = $this->builder->toUpdate( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'UPDATE "testTable" SET "testCol"=:db_prep_1, "moreCol"=:db_prep_2 WHERE "pKey" = :db_prep_3',
            $sql );
        $this->assertEquals( $keyVal, $bind[':db_prep_3'] );
        $this->assertEquals( $values['testCol'], $bind[':db_prep_1'] );
        $this->assertEquals( $values['moreCol'], $bind[':db_prep_2'] );
        $this->assertEquals( 3, count( $bind ) );
    }

    /**
     * @test
     */
    function delete()
    {
        $keyVal = $this->get();
        $this->query->table( 'testTable' )->where(
            Where::column('pKey')->eq($keyVal)
        );
        $sql = $this->builder->toDelete( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'DELETE "testTable" WHERE "pKey" = :db_prep_1',
            $sql );
        $this->assertEquals( $keyVal, $bind[':db_prep_1'] );
        $this->assertEquals( 1, count( $bind ) );
    }

    /**
     * @test
     */
    function select()
    {
        $this->query
            ->table( 'testTable' )
            ->column( 'colTest', 'aliasAs' )
            ->where( Where::column('"my table".name')->like( 'bob' ) )
            ->order( 'pKey' );
        $sql = $this->builder->toSelect( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'SELECT "colTest" AS "aliasAs" FROM "testTable" ' .
            'WHERE "my table"."name" LIKE :db_prep_1 ORDER BY "pKey" ASC',
            $sql );
        $this->assertEquals( 'bob', $bind[':db_prep_1'] );
        $this->assertEquals( 1, count( $bind ) );
    }

    /**
     * @test
     */
    function select_in()
    {
        $in = [
            $this->get(),
            $this->get(),
        ];
        $this->query
            ->table( 'testTable' )
            ->where( Where::column('name')->contain( 'bob' )->status->in($in) )
            ->order( 'pKey' );
        $sql = $this->builder->toSelect( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'SELECT * FROM "testTable" ' .
            'WHERE "name" LIKE :db_prep_1 AND "status" IN ( :db_prep_2, :db_prep_3 ) ' .
            'ORDER BY "pKey" ASC',
            $sql );
        $this->assertEquals( '%bob%', $bind[':db_prep_1'] );
        $this->assertEquals( $in[0], $bind[':db_prep_2'] );
        $this->assertEquals( $in[1], $bind[':db_prep_3'] );
        $this->assertEquals( 3, count( $bind ) );
    }

    /**
     * @test
     */
    function select_between()
    {
        $this->query
            ->table( 'testTable' )
            ->where( Where::column('value')->between(123,345) )
            ->order( 'pKey' );
        $sql = $this->builder->toSelect( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'SELECT * FROM "testTable" ' .
            'WHERE "value" BETWEEN :db_prep_1 AND :db_prep_2 ' .
            'ORDER BY "pKey" ASC',
            $sql );
        $this->assertEquals( '123', $bind[':db_prep_1'] );
        $this->assertEquals( '345', $bind[':db_prep_2'] );
        $this->assertEquals( 2, count( $bind ) );
    }

    /**
     * @test
     */
    function select_isNull_and_no_value_will_be_bound()
    {
        $this->query
            ->table( 'testTable' )
            ->where( Where::column('value')->isNull() );
        $sql = $this->builder->toSelect( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'SELECT * FROM "testTable" ' .
            'WHERE "value" IS NULL',
            $sql );
        $this->assertEmpty( $bind );
    }

    /**
     * @test
     */
    function select_isNotNull_and_no_value_will_be_bound()
    {
        $this->query
            ->table( 'testTable' )
            ->where( Where::column('value')->notNull() );
        $sql = $this->builder->toSelect( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'SELECT * FROM "testTable" ' .
            'WHERE "value" IS NOT NULL',
            $sql );
        $this->assertEmpty( $bind );
    }

    /**
     * @test
     */
    function select_complex_case()
    {
        $this->query
            ->table( 'testTable' )
            ->alias( 'aliasTable' )
            ->forUpdate()
            ->distinct()
            ->column( 'colTest', 'aliasAs' )
            ->where( Where::column('name')->contain( 'bob' ) )
            ->having( Where::column( Sql::raw('COUNT(*)'))->gt(5) )
            ->group( 'grouped' )
            ->order( 'pKey' )
            ->limit(5)
            ->offset(10);
        $this->builder->setDbType( 'pgsql' );
        $sql = $this->builder->toSelect( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'SELECT DISTINCT "colTest" AS "aliasAs" ' .
            'FROM "testTable" "aliasTable" WHERE "name" LIKE :db_prep_1 ' .
            'GROUP BY "grouped" HAVING COUNT(*) > :db_prep_2 ' .
            'ORDER BY "pKey" ASC LIMIT 5 OFFSET 10 FOR UPDATE',
            $sql );
        $this->assertEquals( '%bob%', $bind[':db_prep_1'] );
    }

    /**
     * @test
     */
    function select_using_multiple_set_where()
    {
        $this->query
            ->table( 'testTable' )
            ->where( Where::column('value')->isNull() )
            ->where( Where::column('value')->eq(''), 'or' );
        $sql = $this->builder->toSelect( $this->query );
        $bind = $this->builder->getBind()->getBinding();
        $this->assertEquals(
            'SELECT * FROM "testTable" ' .
            'WHERE "value" IS NULL OR "value" = :db_prep_1',
            $sql );
        $this->assertEquals( '', $bind[':db_prep_1'] );

    }
}