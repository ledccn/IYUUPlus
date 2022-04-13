<?php

$http_raw_post_data = file_get_contents('php://input');

header('Content-Type: text/plain');

echo $http_raw_post_data;
