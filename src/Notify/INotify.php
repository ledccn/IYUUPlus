<?php

namespace IYUU\Notify;

interface INotify {
    function __construct(array $config);
    public function send(string $title, string $content): bool;
}
