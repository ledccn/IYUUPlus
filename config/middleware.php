<?php
/**
 * 中间件配置
 */
return [
    '' => [
        app\middleware\AuthCheck::class,
        //support\middleware\AccessControlTest::class,
    ]
];
