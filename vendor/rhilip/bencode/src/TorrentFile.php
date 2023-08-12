<?php

namespace Rhilip\Bencode;

if (!function_exists('str_contains')) {
    /**
     * polyfill of str_contains for PHP < 8.0.0
     *
     * @param string $haystack
     * @param string $needle
     */
    function str_contains($haystack, $needle)
    {
        return empty($needle) || strpos($haystack, $needle) !== false;
    }
}

/**
 * Additionally, as this is for torrent files, we can make the following assumptions
 * and requirements:
 *  1. Top level data structure must be a dictionary
 *  2. Dictionary must contain an info key
 * If any of these are violated, then we raise an exception for this particular file.
 *
 * @see https://wiki.theory.org/index.php/BitTorrentSpecification
 *
 */
class TorrentFile
{
    // BitTorrent version enumerator
    public const PROTOCOL_V1 = 'v1';  // The original BitTorrent version, using SHA-1 hashes
    public const PROTOCOL_V2 = 'v2';  // Version 2 of the BitTorrent protocol, using SHA-256 hashes
    public const PROTOCOL_HYBRID = 'hybrid';  // Torrent with both v1 and v2 support

    public const FILEMODE_SINGLE = 'single';
    public const FILEMODE_MULTI = 'multi';

    // store torrent dict
    private $data;

    // we may have some tmp data when parse, so we store here to avoid regenerate
    private $cache = [];

    // Custom validator for parse torrent
    private $parseValidator;

    /**
     * Help utils to check torrent dick
     * @param mixed $dict
     * @param string $key
     * @param string|null $type
     */
    protected static function checkTorrentDict($dict, $key, $type = null)
    {
        if (!is_array($dict)) {
            throw new ParseException('Checking non-dictionary value');
        }

        if (!isset($dict[$key])) {
            throw new ParseException("Checking Dictionary missing key: {$key}");
        }

        $value = $dict[$key];

        if (!is_null($type)) {
            $isFunction = 'is_' . $type;
            if (function_exists($isFunction) && !$isFunction($value)) {
                $valueType = gettype($value);
                throw new ParseException("Invalid entry type in dictionary, want : {$type}, current: {$valueType}");
            }
        }

        return $value;
    }

    /**
     * Use as Singleton, so user can only call TorrentFile::load() or
     * TorrentFile::loadFromString() method to create instance.
     */
    protected function __construct($data)
    {
        // Valid must exist key.
        $info = self::checkTorrentDict($data, 'info', 'array');
        self::checkTorrentDict($info, 'piece length', 'integer');
        self::checkTorrentDict($info, 'name', 'string');

        // Store base data
        $this->data = $data;
    }

    /**
     * 1. load and dump method for torrent file
     *
     * - Only load and loadFromString function are static in whole class
     * - dump and dumpToString are just wrapper of Bencode
     */

    public static function load($path)
    {
        return new self(Bencode::load($path));
    }

    public static function loadFromString($string)
    {
        return new self(Bencode::decode($string));
    }

    public function dump($path)
    {
        return Bencode::dump($path, $this->data);
    }

    public function dumpToString()
    {
        return Bencode::encode($this->data);
    }

    /**
     * 2. methods For torrent root dict
     */

    public function getRootData()
    {
        return $this->data;
    }

    public function getRootField($field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }

    public function setRootField($field, $value)
    {
        $this->data[$field] = $value;
        return $this;
    }

    public function unsetRootField($field)
    {
        unset($this->data[$field]);
        return $this;
    }

    /**
     * Clean out keys within the data dictionary that are not strictly necessary or will be
     * overwritten dynamically on any downloaded torrent (like announce or comment), so that we
     * store the smallest encoded string within the database and cuts down on potential waste.
     */
    public function cleanRootFields($allowedKeys = [
        'comment', 'created by', 'creation date' // Other keys
    ])
    {
        $allowedKeys = array_merge([
            'announce', 'info', // main part
            'piece layers', // v2 need
        ], $allowedKeys);
        foreach ($this->data as $key => $value) {
            if (!in_array($key, $allowedKeys)) {
                $this->unsetRootField($key);
            }
        }

        return $this;
    }

    /**
     * 3. getters and setters For torrent root dict
     */

    // Announce
    public function getAnnounce()
    {
        return $this->getRootField('announce');
    }

    public function setAnnounce($value)
    {
        return $this->setRootField('announce', $value);
    }

    // Announce List, see https://www.bittorrent.org/beps/bep_0012.html
    public function getAnnounceList()
    {
        return $this->getRootField('announce-list');
    }

    public function setAnnounceList($value)
    {
        return $this->setRootField('announce-list', $value);
    }

    // Comment, Optional description.
    public function getComment()
    {
        return $this->getRootField('comment');
    }

    public function setComment($value)
    {
        return $this->setRootField('comment', $value);
    }

    // Created By
    public function getCreatedBy()
    {
        return $this->getRootField('created by');
    }

    public function setCreatedBy($value)
    {
        return $this->setRootField('created by', $value);
    }

    // Creation Date
    public function getCreationDate()
    {
        return $this->getRootField('creation date');
    }

    public function setCreationDate($value)
    {
        return $this->setRootField('creation date', $value);
    }

    // Http Seeds, see: https://www.bittorrent.org/beps/bep_0017.html
    public function getHttpSeeds()
    {
        return $this->getRootField('httpseeds');
    }

    public function setHttpSeeds($value)
    {
        return $this->setRootField('httpseeds', $value);
    }

    // Nodes, see: https://www.bittorrent.org/beps/bep_0005.html
    public function getNodes()
    {
        return $this->getRootField('nodes');
    }

    public function setNodes($value)
    {
        return $this->setRootField('nodes', $value);
    }

    // UrlList, see: https://www.bittorrent.org/beps/bep_0019.html
    public function getUrlList()
    {
        return $this->getRootField('url-list');
    }

    public function setUrlList($value)
    {
        return $this->setRootField('url-list', $value);
    }

    /**
     * 4. methods For torrent info dict
     */

    public function getInfoData()
    {
        return $this->data['info'];
    }

    protected function getInfoString()
    {
        return Bencode::encode($this->data['info']);
    }

    public function getInfoField($field, $default = null)
    {
        return $this->data['info'][$field] ?? $default;
    }

    public function setInfoField($field, $value)
    {
        $this->data['info'][$field] = $value;
        return $this;
    }

    public function unsetInfoField($field)
    {
        unset($this->data['info'][$field]);
        return $this;
    }

    /**
     * Cleans out keys within the info dictionary (and would affect the generated info_hash)
     * that are not standard or expected. We do allow some keys that are not strictly necessary
     * (primarily the two below), but that's because it's better to just have the extra bits in
     * the dictionary than having to force a user to re-download the torrent file for something
     * that they might have no idea their client is doing nor how to stop it.
     *
     * x_cross_seed is added by PyroCor (@see https://github.com/pyroscope/pyrocore)
     * unique is added by xseed (@see https://whatbox.ca/wiki/xseed)
     *
     */
    public function cleanInfoFields($allowedKeys = [
        // Other not standard keys
        'name.utf8', 'name.utf-8', 'md5sum', 'sha1', 'source',
        'file-duration', 'file-media', 'profiles',
        'x_cross_seed', 'unique'
    ])
    {
        $allowedKeys = array_merge([
            'name', 'private', 'piece length', // Common key
            'files', 'pieces', 'length',  // v1
            'file tree', 'meta version', // v2
        ], $allowedKeys);
        foreach ($this->data['info'] as $key => $value) {
            if (!in_array($key, $allowedKeys)) {
                $this->unsetInfoField($key);
            }
        }

        return $this;
    }

    /**
     * 5. getters and setters For torrent info dict
     */

    public function getProtocol()
    {
        if (!isset($this->cache['protocol'])) {
            $version = $this->getInfoField('meta version', 1);

            if ($version === 2) {
                $this->cache['protocol'] = $this->getInfoField('pieces') ? self::PROTOCOL_HYBRID : self::PROTOCOL_V2;
            } else {
                $this->cache['protocol'] = self::PROTOCOL_V1;
            }
        }
        return $this->cache['protocol'];
    }

    public function getFileMode()
    {
        if (!isset($this->cache['filemode'])) {
            if ($this->getProtocol() !== self::PROTOCOL_V2) {
                $this->cache['filemode'] = $this->getInfoField('length') ? self::FILEMODE_SINGLE : self::FILEMODE_MULTI;
            } else {
                $fileTree = $this->getInfoField('file tree');

                if (\count($fileTree) !== 1) {
                    $this->cache['filemode'] = self::FILEMODE_MULTI;
                } else {
                    $file = reset($fileTree);

                    if (isset($file['']['length'])) {
                        $this->cache['filemode'] = self::FILEMODE_SINGLE;
                    }
                }
            }
        }

        return $this->cache['filemode'];
    }

    /**
     * Get V1 info hash if V1 metadata is present or null if not.
     *
     * note:
     *  - getInfoHashV1(true) is same as pack("H*", sha1($infohashString))
     *  - getInfoHashv1(false) is same as bin2hex(sha1($infohashString, true))
     */
    public function getInfoHashV1($binary = false)
    {
        if ($this->getProtocol() !== self::PROTOCOL_V2) {
            return sha1($this->getInfoString(), $binary);
        }
    }

    /**
     * Get V2 info hash if V2 metadata is present or null if not.
     *
     * @param bool $binary
     * @throws ParseException
     */
    public function getInfoHashV2($binary = false)
    {
        if ($this->getProtocol() !== self::PROTOCOL_V1) {
            return hash('sha256', $this->getInfoString(), $binary);
        }
    }

    /**
     * The method returns V2 info hash if the metadata is present.
     * Get V2 info hash if V2 metadata is present, fall back to V1 info hash.
     */
    public function getInfoHash($binary = false)
    {
        return $this->getInfoHashV2($binary) ?: $this->getInfoHashV1($binary);
    }

    /**
     * Get all available hashes as array.
     */
    public function getInfoHashs($binary = false)
    {
        return [
            self::PROTOCOL_V1 => $this->getInfoHashV1($binary),
            self::PROTOCOL_V2 => $this->getInfoHashV2($binary)
        ];
    }

    public function getInfoHashV1ForAnnounce()
    {
        return $this->getInfoHashV1(true);
    }

    public function getInfoHashV2ForAnnounce()
    {
        $infoHash = $this->getInfoHashV2(true);
        if ($infoHash) {
            return substr($infoHash, 0, 20);
        }
    }

    /**
     * The 20-bytes truncated infohash
     */
    public function getInfoHashsForAnnounce()
    {
        return [
            self::PROTOCOL_V1 => $this->getInfoHashV1ForAnnounce(),
            self::PROTOCOL_V2 => $this->getInfoHashV2ForAnnounce()
        ];
    }

    public function getPieceLength()
    {
        return $this->getInfoField('piece length');
    }

    public function getName()
    {
        return $this->getInfoField('name.utf8', $this->getInfoField('name'));
    }

    public function setName($name)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('$name must not be empty');
        }
        if (str_contains($name, '/') || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('$name must not contain slashes and zero bytes');
        }

        return $this->setInfoField('name', $name);
    }

    /**
     * Get the source flag if one has been set
     */
    public function getSource()
    {
        return $this->getInfoField('source');
    }

    /**
     * Set the source flag in the info dictionary equal to $source. This can be used to ensure a
     * unique info hash across sites so long as all sites use the source flag. This isn't an
     * 'official' flag (no accepted BEP on it), but it has become the defacto standard with more
     * clients supporting it natively. Returns a boolean on whether or not the source was changed
     * so that an appropriate screen can be shown to the user.
     */
    public function setSource($source)
    {
        $this->unsetInfoField('x_cross_seed')->unsetInfoField('unique');
        return $this->setInfoField('source', $source);
    }

    public function isPrivate()
    {
        return $this->getInfoField('private') === 1;
    }

    /**
     * @param bool $private
     */
    public function setPrivate($private)
    {
        return $private ? $this->setInfoField('private', 1) : $this->unsetInfoField('private');
    }

    /**
     * Get Torrent Magnet URI
     * @return string
     */
    public function getMagnetLink($dn = true, $tr = true)
    {
        $urlSearchParams = [];

        $infoHashV1 = $this->getInfoHashV1();
        if ($infoHashV1) {
            $urlSearchParams[] = 'xt=urn:btih:' . $infoHashV1;
        }

        $infoHashV2 = $this->getInfoHashV2();
        if ($infoHashV2) {
            $urlSearchParams[] = 'xt=urn:btmh:' . '1220' . $infoHashV2;  // 1220 is magic number
        }

        if ($dn) {
            $name = $this->getName() ?? '';
            if ($name !== '') {
                $urlSearchParams[] = 'dn=' . rawurlencode($name);
            }
        }

        if ($tr) {
            $trackers = [];

            $announceList = $this->getAnnounceList();
            if ($announceList) {
                foreach ($announceList as $tier) {
                    foreach ($tier as $tracker) {
                        $trackers[] = $tracker;
                    }
                }
            } else {
                $rootTracker = $this->getAnnounce();
                if ($rootTracker) {
                    $trackers[] = $rootTracker;
                }
            }

            foreach (array_unique($trackers) as $tracker) {
                $urlSearchParams[] = 'tr=' . rawurlencode($tracker);
            }
        }

        return 'magnet:?' . implode('&', $urlSearchParams);
    }

    /**
     * Utility function to clean out keys in the data and info dictionaries that we don't need in
     * our torrent file when we go to store it in the DB or serve it up to the user (with the
     * expectation that we'll be calling at least setAnnounceUrl(...) when a user asks for a valid
     * torrent file).
     */
    public function clean()
    {
        return $this->cleanRootFields()->cleanInfoFields();
    }

    public function setParseValidator($validator = null)
    {
        $this->parseValidator = $validator;
        return $this;
    }

    /**
     * 6. other method that we used to get size, filelist or filetree,
     *
     */
    public function parse()
    {
        if (!isset($this->cache['parsed'])) {
            $totalSize = 0;
            $files = [];
            $fileTree = [];

            $info = $this->data['info'];
            $parseValidator = $this->parseValidator;

            $addFile = function ($paths, $size) use (&$files, &$totalSize, $parseValidator) {
                if ($parseValidator instanceof \Closure) {
                    call_user_func($parseValidator, self::arrayEnd($paths), $paths);
                }
                $totalSize += $size;
                $files[] = ['path' => implode('/', $paths), 'size' => $size];
            };

            if ($this->getProtocol() === self::PROTOCOL_V1) {  // Do what we do in protocol v1
                $pieces = self::checkTorrentDict($info, 'pieces', 'string');
                if (strlen($pieces) % 20 != 0) {
                    throw new ParseException('Invalid pieces length');
                }

                if ($this->getFileMode() === self::FILEMODE_SINGLE) {
                    $size = $this->getInfoField('length');
                    $name = $this->getName();

                    $addFile([$name], $size);
                    $fileTree[$name] = $size;
                } else {
                    $torrentFiles = self::checkTorrentDict($info, 'files', 'array');
                    foreach ($torrentFiles as $file) {
                        $length = self::checkTorrentDict($file, 'length', 'integer');
                        $path_key = isset($file['path.utf-8']) ? 'path.utf-8' : 'path';
                        $paths = self::checkTorrentDict($file, $path_key, 'array');

                        foreach ($paths as $path) {
                            if (!is_string($path)) {
                                throw new ParseException('Invalid path with non-string value');
                            }
                        }

                        $addFile($paths, $length);

                        // Built fileTree for v1-multi torrent
                        $leafPart = array_pop($paths);
                        $parentArr = &$fileTree;
                        foreach ($paths as $path) {
                            if (!isset($parentArr[$path])) {
                                $parentArr[$path] = [];
                            } elseif (!is_array($parentArr[$path])) {
                                $parentArr[$path] = [];
                            }
                            $parentArr = &$parentArr[$path];
                        }
                        if (empty($parentArr[$leafPart])) {
                            $parentArr[$leafPart] = $length;
                        }
                    }
                }
            } else {
                $pieceLength = $this->getPieceLength();
                $fileTree = self::checkTorrentDict($info, 'file tree', 'array');
                $pieceLayers = self::checkTorrentDict($this->data, 'piece layers', 'array');

                $loopMerkleTree = function (&$merkleTree, &$paths = []) use (&$files, &$size, $pieceLayers, $addFile, $pieceLength, &$loopMerkleTree) {
                    if (isset($merkleTree[''])) {  // reach file
                        $file = $merkleTree[''];

                        $piecesRoot = self::checkTorrentDict($file, 'pieces root', 'string');
                        if (strlen($piecesRoot) != 32) {
                            throw new ParseException('Invalid pieces_root length.');
                        }

                        $length = self::checkTorrentDict($file, 'length', 'integer');
                        if ($length > $pieceLength) {  // check pieces root of large file is exist in $root['picec layers'] or not
                            if (!array_key_exists($piecesRoot, $pieceLayers)) {
                                throw new ParseException('Pieces not exist in piece layers');
                            }
                        }

                        $addFile($paths, $length);
                        $merkleTree = $length;  // rewrite merkleTree to size, it's safe since it not affect $data['info']['file tree']
                    } else {
                        $parent_path = $paths;  // store parent paths
                        foreach ($merkleTree as $k => &$v) {  // Loop tree
                            $paths[] = $k;   // push current path into paths
                            $loopMerkleTree($v, $paths);  // Loop check
                            $paths = $parent_path;  // restore parent paths
                        }
                    }
                };

                $loopMerkleTree($fileTree);
            }

            // Fix fileTree for multi torrent
            if ($this->getFileMode() === self::FILEMODE_MULTI) {
                $torrentName = $this->getName();
                $fileTree = [$torrentName => $fileTree];
            }

            $this->cache['parsed'] = [
                'total_size' => $totalSize,
                'count' => count($files),
                'files' => $files,
                'fileTree' => $fileTree
            ];
        }
        return $this->cache['parsed'];
    }

    public function getSize()
    {
        return $this->parse()['total_size'];
    }

    public function getFileCount()
    {
        return $this->parse()['count'];
    }

    /**
     * Return a list like:
     * [
     *   ["path" => "filename1", "size" => 123],   //  123 is file size
     *   ["path" => "directory/filename2", "size" => 2345]
     * ]
     *
     */
    public function getFileList()
    {
        return $this->parse()['files'];
    }

    /**
     * Return a dict like:
     * [
     *     "torrentname" => [
     *         "directory" => [
     *             "filename2" => 2345
     *         ],
     *         "filename1" => 123
     *    ]
     * ]
     */
    public function getFileTree()
    {
        return $this->parse()['fileTree'];
    }

    public function cleanCache()
    {
        $this->cache = [];
        return $this;
    }

    // Wrapper end function to avoid change the internal pointer of $path,
    private static function arrayEnd($array)
    {
        return end($array);
    }
}
