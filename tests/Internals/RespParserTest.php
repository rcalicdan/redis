<?php

declare(strict_types=1);

use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Internals\Protocol\RespParser;

it('parses simple strings (+)', function () {
    $parser = new RespParser();
    $parser->append("+OK\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe('OK')
    ;
});

it('parses errors (-) into RedisException', function () {
    $parser = new RespParser();
    $parser->append("-ERR unknown command\r\n");

    /** @var RedisException|null */
    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBeInstanceOf(RedisException::class)
        ->and($result->getMessage())->toBe('ERR unknown command')
    ;
});

it('parses integers (:)', function () {
    $parser = new RespParser();
    $parser->append(":1000\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe(1000)
    ;
});

it('parses bulk strings ($)', function () {
    $parser = new RespParser();
    $parser->append("$6\r\nfoobar\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe('foobar')
    ;
});

it('parses null bulk strings', function () {
    $parser = new RespParser();
    $parser->append("$-1\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBeNull()
    ;
});

it('parses arrays (*)', function () {
    $parser = new RespParser();
    $parser->append("*2\r\n$3\r\nfoo\r\n$3\r\nbar\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe(['foo', 'bar'])
    ;
});

it('parses null arrays', function () {
    $parser = new RespParser();
    $parser->append("*-1\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBeNull()
    ;
});

it('parses nested arrays', function () {
    $parser = new RespParser();
    $parser->append("*2\r\n*1\r\n:1\r\n*1\r\n:2\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe([[1], [2]])
    ;
});

it('yields and waits when TCP chunk is incomplete', function () {
    $parser = new RespParser();
    $result = null;

    $parser->append("$6\r");
    expect($parser->parse($result))->toBeFalse();

    $parser->append("\nfoo");
    expect($parser->parse($result))->toBeFalse();

    $parser->append("bar\r\n");
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe('foobar')
    ;
});

it('drains multiple packets from a single TCP chunk', function () {
    $parser = new RespParser();
    $parser->append("+OK\r\n:42\r\n");

    $result1 = null;
    expect($parser->parse($result1))->toBeTrue()
        ->and($result1)->toBe('OK')
    ;

    $result2 = null;
    expect($parser->parse($result2))->toBeTrue()
        ->and($result2)->toBe(42)
    ;

    $result3 = null;
    expect($parser->parse($result3))->toBeFalse();
});

it('throws a RuntimeException on invalid RESP type', function () {
    $parser = new RespParser();
    $parser->append("?unknown_type\r\n");

    $result = null;
    $parser->parse($result);
})->throws(RuntimeException::class, 'Protocol error, unknown RESP type: ?');

it('parses empty bulk strings', function () {
    $parser = new RespParser();
    $parser->append("$0\r\n\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe('')
    ;
});

it('parses empty arrays', function () {
    $parser = new RespParser();
    $parser->append("*0\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe([])
    ;
});

it('parses negative integers', function () {
    $parser = new RespParser();
    $parser->append(":-12345\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe(-12345)
    ;
});

it('parses bulk strings containing CRLF and null bytes internally', function () {
    $parser = new RespParser();
    $parser->append("$11\r\nfoo\r\nbar\0ba\r\n");

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe("foo\r\nbar\0ba")
    ;
});

it('parses complex mixed arrays', function () {
    $parser = new RespParser();

    $payload = "*5\r\n:42\r\n+OK\r\n$-1\r\n$4\r\ntest\r\n*1\r\n:1\r\n";
    $parser->append($payload);

    $result = null;
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe([
            42,
            'OK',
            null,
            'test',
            [1],
        ])
    ;
});

it('yields when CRLF is split across chunks', function () {
    $parser = new RespParser();
    $result = null;

    $parser->append("+OK\r");
    expect($parser->parse($result))->toBeFalse();

    $parser->append("\n");
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe('OK')
    ;
});

it('handles extreme fragmentation (1 byte at a time)', function () {
    $parser = new RespParser();
    $result = null;

    $payload = "*2\r\n$4\r\nping\r\n:100\r\n";
    $length = strlen($payload);

    for ($i = 0; $i < $length - 1; $i++) {
        $parser->append($payload[$i]);
        expect($parser->parse($result))->toBeFalse();
    }

    $parser->append($payload[$length - 1]);
    expect($parser->parse($result))->toBeTrue()
        ->and($result)->toBe(['ping', 100])
    ;
});

it('drains multiple packets perfectly without bleeding bytes', function () {
    $parser = new RespParser();

    $payload = "$3\r\nfoo\r\n+BAR\r\n";
    $parser->append($payload);

    $result1 = null;
    expect($parser->parse($result1))->toBeTrue()
        ->and($result1)->toBe('foo')
    ;

    $result2 = null;
    expect($parser->parse($result2))->toBeTrue()
        ->and($result2)->toBe('BAR');

    $result3 = null;
    expect($parser->parse($result3))->toBeFalse();
});
