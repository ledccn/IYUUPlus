<?php

include_once 'traits/TorrentFileCommonTrait.php';
include_once 'traits/TorrentFileV1Trait.php';

use Rhilip\Bencode\ParseException;
use Rhilip\Bencode\TorrentFile;
use PHPUnit\Framework\TestCase;

class TorrentV1MultiTest extends TestCase
{
    use TorrentFileCommonTrait;
    use TorrentFileV1Trait;

    protected $protocol = TorrentFile::PROTOCOL_V1;
    protected $fileMode = TorrentFile::FILEMODE_MULTI;

    protected $infoHashs = [
        TorrentFile::PROTOCOL_V1 => '344f85b35113783a34bb22ba7661fa26f1046bd1',
        TorrentFile::PROTOCOL_V2 => null
    ];

    public function testFilesNotExist() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Checking Dictionary missing key: ');

        $this->torrent->unsetInfoField('files');
        $this->torrent->parse();
    }

    public function testFilesNotArray() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid entry type in dictionary, ');

        $this->torrent->setInfoField('files', 'somestring');
        $this->torrent->parse();
    }

    public function testFilesLengthNotInt() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid entry type in dictionary, ');

        $files = $this->torrent->getInfoField('files');
        $files[0]['length'] = '12345667';

        $this->torrent->setInfoField('files', $files);
        $this->torrent->parse();
    }

    public function testFilesPathNotArray() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid entry type in dictionary, ');

        $files = $this->torrent->getInfoField('files');
        $files[0]['path'] = '12345667';

        $this->torrent->setInfoField('files', $files);
        $this->torrent->parse();
    }

    public function testFilesPathEntityNotString() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid path with non-string value');

        $files = $this->torrent->getInfoField('files');
        $files[0]['path'][0] = 123;

        $this->torrent->setInfoField('files', $files);
        $this->torrent->parse();
    }
}
