<?php

use Rhilip\Bencode\ParseException;
use Rhilip\Bencode\TorrentFile;

trait TorrentFileCommonTrait
{
    /** @var TorrentFile */
    private $torrent;

    protected $announce = 'https://example.com/announce';
    protected $announceList = [
        ["https://example.com/announce"],
        ["https://example1.com/announce"]
    ];
    protected $comment = 'Rhilip';
    protected $createdBy = 'qBittorrent v4.4.2';
    protected $creationDate = 1648385760;
    protected $urlList = "https://example.com/webseed";

    protected $pieceLength = 65536;
    protected $source = 'Rhilip';

    protected function setUp(): void
    {
        $this->torrent = TorrentFile::load("tests/asserts/{$this->protocol}-{$this->fileMode}.torrent");
    }

    public function testAnnounce()
    {
        $this->assertEquals($this->announce, $this->torrent->getAnnounce());

        $announce = 'https://example2.com/announce';
        $this->torrent->setAnnounce($announce);
        $this->assertEquals($announce, $this->torrent->getAnnounce());
    }

    public function testAnnounceList()
    {
        $this->assertEquals($this->announceList, $this->torrent->getAnnounceList());

        $announceList = [["https://example1.com/announce"], ["https://example2.com/announce"]];
        $this->torrent->setAnnounceList($announceList);
        $this->assertEquals($announceList, $this->torrent->getAnnounceList());
    }

    public function testComment()
    {
        $this->assertEquals($this->comment, $this->torrent->getComment());

        $comment = 'new comment';
        $this->torrent->setComment($comment);
        $this->assertEquals($comment, $this->torrent->getComment());
    }

    public function testCreatBy()
    {
        $this->assertEquals($this->createdBy, $this->torrent->getCreatedBy());

        $createdBy = 'new createdBy';
        $this->torrent->setCreatedBy($createdBy);
        $this->assertEquals($createdBy, $this->torrent->getCreatedBy());
    }

    public function testCreationDate()
    {
        $this->assertEquals($this->creationDate, $this->torrent->getCreationDate());

        $creationDate = time();
        $this->torrent->setCreationDate($creationDate);
        $this->assertEquals($creationDate, $this->torrent->getCreationDate());
    }

    public function testHttpSeeds()
    {
        $this->assertNull($this->torrent->getHttpSeeds());

        $httpSeeds = ['udp://example.com/seed'];
        $this->torrent->setHttpSeeds($httpSeeds);
        $this->assertEquals($httpSeeds, $this->torrent->getHttpSeeds());
    }

    public function testNodes()
    {
        $this->assertNull($this->torrent->getNodes());

        $nodes = ['udp://example.com/seed'];
        $this->torrent->setNodes($nodes);
        $this->assertEquals($nodes, $this->torrent->getNodes());
    }

    public function testUrlList()
    {
        $this->assertEquals($this->urlList, $this->torrent->getUrlList());

        $urlList = "https://example1.com/webseed";
        $this->torrent->setUrlList($urlList);
        $this->assertEquals($urlList, $this->torrent->getUrlList());
    }

    public function testGetProtocol()
    {
        $this->assertEquals($this->protocol, $this->torrent->getProtocol());
    }

    public function testgetFileMode()
    {
        $this->assertEquals($this->fileMode, $this->torrent->getFileMode());
    }

    public function testInfoHash()
    {
        $this->assertEquals($this->infoHashs, $this->torrent->getInfoHashs());

        if ($this->protocol === TorrentFile::PROTOCOL_V1) {
            $this->assertEquals($this->infoHashs[TorrentFile::PROTOCOL_V1], $this->torrent->getInfoHashV1());
            $this->assertEquals($this->infoHashs[TorrentFile::PROTOCOL_V1], $this->torrent->getInfoHash());
        }

        if ($this->protocol === TorrentFile::PROTOCOL_V2) {
            $this->assertEquals($this->infoHashs[TorrentFile::PROTOCOL_V2], $this->torrent->getInfoHashV2());
            $this->assertEquals($this->infoHashs[TorrentFile::PROTOCOL_V2], $this->torrent->getInfoHash());
            $this->assertEquals(substr(pack("H*", $this->infoHashs[TorrentFile::PROTOCOL_V2]), 0, 20), $this->torrent->getInfoHashV2ForAnnounce());
        }

        if ($this->protocol === TorrentFile::PROTOCOL_HYBRID) {
            $this->assertEquals($this->infoHashs[TorrentFile::PROTOCOL_V1], $this->torrent->getInfoHashV1());
            $this->assertEquals($this->infoHashs[TorrentFile::PROTOCOL_V2], $this->torrent->getInfoHashV2());
            $this->assertEquals($this->infoHashs[TorrentFile::PROTOCOL_V2], $this->torrent->getInfoHash());
            $this->assertEquals(substr(pack("H*", $this->infoHashs[TorrentFile::PROTOCOL_V2]), 0, 20), $this->torrent->getInfoHashV2ForAnnounce());
        }
    }

    public function testInfoHashNotChangeAfterParse()
    {
        $this->torrent->parse();
        $this->testInfoHash();
    }

    public function testGetPieceLength()
    {
        $this->assertEquals($this->pieceLength, $this->torrent->getPieceLength());
    }

    public function testGetName()
    {
        $name = $this->fileMode === TorrentFile::FILEMODE_MULTI ? 'tname' : 'file1.dat';

        $this->assertEquals($name, $this->torrent->getName());
    }

    public function testSetNameEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name must not be empty');

        $this->torrent->setName('');
    }

    public function testSetNameWithSlash()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name must not contain slashes and zero bytes');

        $this->torrent->setName('prefix/suffix');
    }

    public function testSetNameWithZeroBytes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$name must not contain slashes and zero bytes');

        $this->torrent->setName("test\0");
    }

    public function testSource()
    {
        $this->assertEquals($this->source, $this->torrent->getSource());

        $source = "new source";
        $this->torrent->setSouce($source);
        $this->assertEquals($source, $this->torrent->getSource());
    }

    public function testInfoChangeByEdit(){
        $infoHashs = $this->torrent->getInfoHashs();

        $this->torrent->setInfoField('rhilip','bencode');

        // infohash should change since we edit info field
        $this->assertNotEqualsCanonicalizing($infoHashs, $this->torrent->getInfoHashs());
    }

    public function testPrivate()
    {
        $this->assertFalse($this->torrent->isPrivate());

        $this->torrent->setPrivate(true);
        $this->assertTrue($this->torrent->isPrivate());

        $this->torrent->setPrivate(false);
        $this->assertFalse($this->torrent->isPrivate());
    }

    public function testGetSize()
    {
        $size = $this->fileMode === TorrentFile::FILEMODE_MULTI ? 33554432 /* 32MiB */ : 16777216 /* 16MiB */;
        $this->assertEquals($size, $this->torrent->getSize());
    }

    public function testGetFileCount()
    {
        $fileCount = $this->fileMode === TorrentFile::FILEMODE_MULTI ? 2 : 1;
        $this->assertEquals($fileCount, $this->torrent->getFileCount());
    }

    public function testGetFileList()
    {
        $fileCount = $this->fileMode === TorrentFile::FILEMODE_MULTI
            ? [['path' => 'dict/file2.dat', 'size' => 16777216], ['path' => 'file1.dat', 'size' => 16777216]]
            : [['path' => 'file1.dat', 'size' => 16777216]];
        $this->assertEqualsCanonicalizing($fileCount, $this->torrent->getFileList());
    }

    public function testGetFileTree()
    {
        $fileCount = $this->fileMode === TorrentFile::FILEMODE_MULTI
            ? ['tname' => ['dict' => ['file2.dat' => 16777216], 'file1.dat' => 16777216]]
            : ['file1.dat' => 16777216];
        $this->assertEqualsCanonicalizing($fileCount, $this->torrent->getFileTree());
    }

    public function testConstructWithoutInfo()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Checking Dictionary missing key: ');

        $torrentString = $this->torrent->unsetRootField('info')->dumpToString();
        TorrentFile::loadFromString($torrentString);
    }

    public function testConstructWithoutPieceLength()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Checking Dictionary missing key: ');

        $torrentString = $this->torrent->unsetInfoField('piece length')->dumpToString();
        TorrentFile::loadFromString($torrentString);
    }

    public function testConstructWithoutName()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Checking Dictionary missing key: ');

        $torrentString = $this->torrent->unsetInfoField('name')->dumpToString();
        TorrentFile::loadFromString($torrentString);
    }

    public function testCleanRootFields()
    {
        $this->torrent->setRootField('rhilip', 'bencode');
        $this->assertEquals('bencode', $this->torrent->getRootField('rhilip'));

        $this->torrent->cleanRootFields();
        $this->assertNull($this->torrent->getRootField('rhilip'));
    }

    public function testCleanInfoFields()
    {
        $this->torrent->setInfoField('rhilip', 'bencode');
        $this->assertEquals('bencode', $this->torrent->getInfoField('rhilip'));

        $this->torrent->cleanInfoFields();
        $this->assertNull($this->torrent->getInfoField('rhilip'));
    }

    public function testCustomParseValidator()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('file1.dat found');

        $this->torrent->setParseValidator(function ($filename, $path) {
           if (strpos($filename, 'file1.dat') !== false) {
               throw new ParseException('file1.dat found');
           }
        });
        $this->torrent->parse();
    }
}
