# PHP Bencode Library
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FRhilip%2FBencode.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2FRhilip%2FBencode?ref=badge_shield)


[Bencode](https://en.wikipedia.org/wiki/Bencode) is the encoding used by the peer-to-peer file sharing system
[BitTorrent](https://opensource.org/licenses/MIT) for storing and transmitting loosely structured data.

This is a pure PHP library that allows you to encode and decode Bencode data.

This library is fork from [OPSnet/bencode-torrent](https://github.com/OPSnet/bencode-torrent),
with same method like [sandfoxme/bencode](https://github.com/sandfoxme/bencode)

## Installation

```shell script
composer require rhilip/bencode
```

## Usage

```php
<?php

require '/path/to/vendor/autoload.php';

use Rhilip\Bencode\Bencode;
use Rhilip\Bencode\ParseErrorException;

// Decodes a BEncoded string
Bencode::decode($string);

// Encodes string/array/int to a BEncoded string
Bencode::encode($data);

// Decodes a BEncoded file From path
Bencode::load($path); 

// Encodes string/array/int to a BEncoded file
Bencode::dump($path, $data); 

// With Error Catch
try {
    Bencode::decode('wrong_string');
 } catch (ParseErrorException $e) {
    // do something
} 
```

## License

The library is available as open source under the terms of the [MIT License](https://opensource.org/licenses/MIT).

[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FRhilip%2FBencode.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2FRhilip%2FBencode?ref=badge_large)