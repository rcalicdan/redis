<?php

declare(strict_types=1);

use Hibla\Redis\Internals\Protocol\CommandBuilder;

it('builds a simple command without arguments', function () {
    $payload = CommandBuilder::build(['PING']);

    expect($payload)->toBe("*1\r\n$4\r\nPING\r\n");
});

it('builds a command with arguments', function () {
    $payload = CommandBuilder::build(['SET', 'mykey', 'myvalue']);

    expect($payload)->toBe("*3\r\n$3\r\nSET\r\n$5\r\nmykey\r\n$7\r\nmyvalue\r\n");
});

it('builds a command with integer and float arguments', function () {
    $payload = CommandBuilder::build(['INCRBYFLOAT', 'counter', 10.5]);

    expect($payload)->toBe("*3\r\n$11\r\nINCRBYFLOAT\r\n$7\r\ncounter\r\n$4\r\n10.5\r\n");
});

it('handles empty strings and nulls correctly', function () {
    $payload = CommandBuilder::build(['SET', 'key', '', null]);

    expect($payload)->toBe("*4\r\n$3\r\nSET\r\n$3\r\nkey\r\n$0\r\n\r\n$0\r\n\r\n");
});

it('accepts Stringable objects', function () {
    $stringable = new class () implements Stringable {
        public function __toString(): string
        {
            return 'stringable_value';
        }
    };

    $payload = CommandBuilder::build(['SET', 'key', $stringable]);

    expect($payload)->toBe("*3\r\n$3\r\nSET\r\n$3\r\nkey\r\n$16\r\nstringable_value\r\n");
});

it('throws an exception for invalid argument types', function () {
    CommandBuilder::build(['SET', 'key', ['invalid_array']]);
})->throws(InvalidArgumentException::class, 'Redis command arguments must be scalar, Stringable, or null');

it('builds commands with binary unsafe characters (null bytes, CRLF)', function () {
    $binaryData = "line1\r\nline2\0line3";
    $payload = CommandBuilder::build(['SET', 'binary_key', $binaryData]);

    expect($payload)->toBe("*3\r\n$3\r\nSET\r\n$10\r\nbinary_key\r\n$18\r\nline1\r\nline2\0line3\r\n");
});

it('handles boolean arguments by casting them to strings', function () {
    $payload = CommandBuilder::build(['SET', 'flag', true, false]);

    expect($payload)->toBe("*4\r\n$3\r\nSET\r\n$4\r\nflag\r\n$1\r\n1\r\n$0\r\n\r\n");
});

it('builds an empty array if no arguments are provided', function () {
    $payload = CommandBuilder::build([]);

    expect($payload)->toBe("*0\r\n");
});

it('handles extremely large integers and floats', function () {
    $payload = CommandBuilder::build(['SET', 'bignum', PHP_INT_MAX, 1.23e+10]);

    $intStr = (string) PHP_INT_MAX;
    $floatStr = (string) 1.23e+10;
    $intLen = strlen($intStr);
    $floatLen = strlen($floatStr);

    expect($payload)->toBe("*4\r\n$3\r\nSET\r\n$6\r\nbignum\r\n\${$intLen}\r\n{$intStr}\r\n\${$floatLen}\r\n{$floatStr}\r\n");
});
