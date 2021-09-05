<?php
/**
 * 中间件配置
 */
return [
    '' => [
        app\middleware\AuthCheck::class,
        app\middleware\ActionHook::class,
        //support\middleware\AuthCheckTest::class,
        //support\middleware\AccessControlTest::class,
    ]
];
