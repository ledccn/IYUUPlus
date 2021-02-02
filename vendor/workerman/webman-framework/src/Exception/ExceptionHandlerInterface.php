<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Webman\Exception;

use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

interface ExceptionHandlerInterface
{
    /**
     * @param Throwable $e
     * @return mixed
     */
    public function report(Throwable $e);

    /**
     * @param Request $request
     * @param Throwable $e
     * @return Response
     */
    public function render(Request $request, Throwable $e) : Response;
}