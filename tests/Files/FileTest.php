<?php

namespace Tests\Files;

use RunTests\Files\File;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    public function testCanReadLinesFromAFile()
    {
        $file = new File(__DIR__.'/../phpt-files/test012.txt');
        $file->open();
        $this->assertSame("%d\n", $file->line()->current());
        $this->assertSame("%i\n", $file->line()->current());
        $file->close();
    }
}