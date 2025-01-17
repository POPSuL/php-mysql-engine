<?php
namespace Vimeo\MysqlEngine\Parser;

use Vimeo\MysqlEngine\TokenType;
use Vimeo\MysqlEngine\Query\TruncateQuery;

final class TruncateParser
{
    /**
     * @var int
     */
    private $pointer = 0;

    /**
     * @var array<int, Token>
     */
    private $tokens;

    /**
     * @var string
     */
    private $sql;

    /**
     * @param array<int, Token> $tokens
     */
    public function __construct(array $tokens, string $sql)
    {
        $this->tokens = $tokens;
        $this->sql = $sql;
    }

    public function parse() : TruncateQuery
    {
        if ($this->tokens[$this->pointer]->value !== 'TRUNCATE') {
            throw new SQLFakeParseException("Parser error: expected TRUNCATE");
        }

        $this->pointer++;

        $token = $this->tokens[$this->pointer];

        if ($token === null || $token->type !== TokenType::IDENTIFIER) {
            throw new SQLFakeParseException("Expected table name after TRUNCATE");
        }

        return new TruncateQuery($token->value, $this->sql);
    }
}
