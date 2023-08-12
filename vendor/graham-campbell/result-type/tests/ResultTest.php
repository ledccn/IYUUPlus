<?php

declare(strict_types=1);

/*
 * This file is part of Result Type.
 *
 * (c) Graham Campbell <hello@gjcampbell.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GrahamCampbell\Tests\ResultType;

use GrahamCampbell\ResultType\Error;
use GrahamCampbell\ResultType\Success;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResultTest extends TestCase
{
    public function testSuccessValue(): void
    {
        $this->assertTrue(Success::create('foo')->error()->isEmpty());
        $this->assertTrue(Success::create('foo')->success()->isDefined());
        $this->assertSame('foo', Success::create('foo')->success()->get());
    }

    public function testSuccessMapping(): void
    {
        $r = Success::create('foo')
            ->map('strtoupper')
            ->mapError('ucfirst');

        $this->assertTrue($r->success()->isDefined());
        $this->assertSame('FOO', $r->success()->get());
    }

    public function testSuccessFlatMappingSuccess(): void
    {
        $r = Success::create('foo')->flatMap(function (string $data): Success {
            return Success::create('OH YES');
        });

        $this->assertTrue($r->success()->isDefined());
        $this->assertSame('OH YES', $r->success()->get());
    }

    public function testSuccessFlatMappingError(): void
    {
        $r = Success::create('foo')->flatMap(function (string $data): Error {
            return Error::create('OH NO');
        });

        $this->assertTrue($r->error()->isDefined());
        $this->assertSame('OH NO', $r->error()->get());
    }

    public function testSuccessFail(): void
    {
        $result = Success::create('foo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('None has no value.');

        $result->error()->get();
    }

    public function testErrorValue(): void
    {
        $this->assertTrue(Error::create('foo')->error()->isDefined());
        $this->assertTrue(Error::create('foo')->success()->isEmpty());
        $this->assertSame('foo', Error::create('foo')->error()->get());
    }

    public function testErrorMapping(): void
    {
        $r = Error::create('foo')
            ->map('strtoupper')
            ->mapError('ucfirst');

        $this->assertSame('Foo', $r->error()->get());
    }

    public function testErrorFail(): void
    {
        $result = Error::create('foo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('None has no value.');

        $result->success()->get();
    }
}
