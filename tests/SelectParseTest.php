<?php
namespace Vimeo\MysqlEngine\Tests;

class SelectParseTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleParse()
    {
        $query = 'SELECT `foo` FROM `bar` WHERE `id` = 1';

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($query);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testCast()
    {
        $query = 'SELECT CAST(1 + 2 AS UNSIGNED) as `a`';

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($query);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);

        $conn = new \Vimeo\MysqlEngine\FakePdo('mysql:foo');

        $this->assertSame(
            [['a' => 3]],
            \Vimeo\MysqlEngine\Processor\SelectProcessor::process($conn, $select_query, null)
        );
    }

    public function testAddFunctionResults()
    {
        $query = 'SELECT IFNULL(`a`.`b`, 0) + ISNULL(`a`.`c`)';

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($query);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);

        $this->assertCount(1, $select_query->selectExpressions);

        $this->assertInstanceOf(
            \Vimeo\MysqlEngine\Query\Expression\BinaryOperatorExpression::class,
            $select_query->selectExpressions[0]
        );

        $this->assertTrue($select_query->selectExpressions[0]->isWellFormed());

        $this->assertSame('IFNULL(`a`.`b`, 0) + ISNULL(`a`.`c`)', $select_query->selectExpressions[0]->name);
    }

    public function testSubqueryCalculation()
    {
        $query = 'SELECT (SELECT 2) + (SELECT 3) as `a`';

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($query);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);

        $conn = new \Vimeo\MysqlEngine\FakePdo('mysql:foo');

        $this->assertSame(
            [['a' => 5]],
            \Vimeo\MysqlEngine\Processor\SelectProcessor::process($conn, $select_query, null)
        );
    }

    public function testFunctionOperatorPrecedence()
    {
        $sql = 'SELECT SUM(`a`.`foo` - IFNULL(`b`.`bar`, 0) - `c`.`baz`) as `a`';

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);

        $this->assertInstanceOf(
            \Vimeo\MysqlEngine\Query\Expression\FunctionExpression::class,
            $select_query->selectExpressions[0]
        );

        $sum_function = $select_query->selectExpressions[0];

        $this->assertTrue(isset($sum_function->args[0]));

        $this->assertInstanceOf(
            \Vimeo\MysqlEngine\Query\Expression\BinaryOperatorExpression::class,
            $sum_function->args[0]
        );

        $this->assertInstanceOf(
            \Vimeo\MysqlEngine\Query\Expression\BinaryOperatorExpression::class,
            $sum_function->args[0]->left
        );
    }

    public function testWrappedSubquery()
    {
        $sql = 'SELECT * FROM (((SELECT 5))) AS all_parts';
        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testCaseWhenNotExists()
    {
        $sql = "SELECT CASE WHEN NOT EXISTS (SELECT * FROM `bar`) THEN 'BAZ' ELSE NULL END FROM `bam`";

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testInterval()
    {
        $sql = 'SELECT DATE_ADD(\'2008-01-02\', INTERVAL 31 DAY)';

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testUnionInSubquery()
    {
        $sql = "SELECT *
                FROM  ((SELECT * FROM `c`) UNION ALL (SELECT * FROM `d`)) AS `bar`";

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testAndNotExists()
    {
        $sql = "SELECT *
                FROM `foo`
                WHERE `a` > 0
                AND NOT EXISTS (SELECT * FROM `bar`)";

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testSumCaseMultiplied()
    {
        $sql = "SELECT SUM((CASE WHEN `a`.`b` THEN 1 ELSE 0 END) * (CASE WHEN `a`.`c` THEN 1 ELSE 0 END))
                 FROM `foo`";

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testBadAs()
    {
        $sql = "SELECT (@refund_date := `ordered_transactions`.`refund_date`) as refund_date FROM `foo`";

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
    }

    public function testDateArithhmetic()
    {
        $query = 'SELECT DATE_SUB(\'2020-03-01 12:00:00\', INTERVAL 1 HOUR) as `a`,
                        DATE_ADD(\'2020-03-01 12:00:00\', INTERVAL 1 HOUR) as `b`';

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($query);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);

        $conn = new \Vimeo\MysqlEngine\FakePdo('mysql:foo');

        $this->assertSame(
            [['a' => '2020-03-01 11:00:00', 'b' => '2020-03-01 13:00:00']],
            \Vimeo\MysqlEngine\Processor\SelectProcessor::process($conn, $select_query, null)
        );
    }

    public function testParseComplexJoin()
    {
        $sql = "SELECT * FROM (SELECT * FROM `foo` UNION ALL SELECT * FROM `bar`) AS `baz`";

        $select_query = \Vimeo\MysqlEngine\Parser\SQLParser::parse($sql);

        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\SelectQuery::class, $select_query);
        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\FromClause::class, $select_query->fromClause);
        $this->assertCount(1, $select_query->fromClause->tables);
        $this->assertInstanceOf(\Vimeo\MysqlEngine\Query\Expression\SubqueryExpression::class, $select_query->fromClause->tables[0]['subquery']);
        $this->assertNotEmpty($select_query->fromClause->tables[0]['subquery']->query->multiQueries);
    }
}
