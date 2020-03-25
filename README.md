[TOC]

# 安装composer以及组件

## 安装composer

### 安装前准备
在composer中添加依赖包
```php
"lcobucci/jwt": "^3.2",
"jcchavezs/zipkin-opentracing": "^0.1.2",
"opentracing/opentracing": "1.0.0-beta5",
"hanier/swoft": "dev-master",
"hanier/zipkin": "dev-master"
```
修改或添加composer中的repositories和config
```php

执行composer install --prefer-dist
*一定要加上--prefer-dist参数，这样加载的包里面才没有.git目录*
## 安装组件

目前一共有4个组件，这4个组件都经过修改兼容swoft-zipkin的监控。

**如果之前没有安装过组件，那每个组件前两条命令只需要执行一次，之后再更新组件直接使用第三条命令pull即可。**

#### http-client组件
```
rm -rf vendor/swoft/http-client
git subtree add --prefix=vendor/swoft/http-client git@github.com:hanierming/swoft-http-client.git master --squash
git subtree pull --prefix=vendor/swoft/http-client git@github.com:hanierming/swoft-http-client.git master --squash
```

#### db组件
```
rm -rf vendor/swoft/db
git subtree add --prefix=vendor/swoft/db git@github.com:hanierming/swoft-db.git master --squash
git subtree pull --prefix=vendor/swoft/db git@github.com:hanierming/swoft-db.git master --squash
```

#### redis组件
```
rm -rf vendor/swoft/redis
git subtree add --prefix=vendor/swoft/redis git@github.com:hanierming/swoft-redis.git master --squash
git subtree pull --prefix=vendor/swoft/redis git@github.com:hanierming/swoft-redis.git master --squash
```

#### opentracing组件
```
rm -rf vendor/opentracing/opentracing/

git subtree add --prefix=vendor/opentracing/opentracing git@github.com:hanierming/swoft-opentracing.git master --squash

git subtree pull --prefix=vendor/opentracing/opentracing git@github.com:hanierming/swoft-opentracing.git master --squash
```

## 使用zipkin

#### 在config/properties/app.php 添加
```php
    'components' => [
        'custom' => [
            "Hanier\\Swoft\\",
            "Hanier\\Zipkin\\"
        ],
    ],
```

#### 在config/beans/base.php的中间件数组中添加中间件
```php
\Hanier\Swoft\Middlewares\ZipkinMiddleware::class
```
*zipkin需要在全局应用，所以在这里加载全局的zipkin中间件*

#### 在.env增加配置
```php
PNAME=test  //原来就有这个参数，代表项目名
ZIPKIN_HOST=http://localhost:9411   //收集上报域名
ZIPKIN_URI='/api/v2/spans'   //收集上报URI
ZIPKIN_RAND=100  //收集概率
```

#### 修改exception，用zipkin捕获错误异常
修改app/Exception/SwoftExceptionHandler.php为
```php
<?php
/**
 * This file is part of Swoft.
 *
 * @link https://swoft.org
 * @document https://doc.swoft.org
 * @contact group@swoft.org
 * @license https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace App\Exception;

use Hanier\Zipkin\Manager\TracerManager;
use OpenTracing\GlobalTracer;
use Swoft\App;
use Swoft\Bean\Annotation\ExceptionHandler;
use Swoft\Bean\Annotation\Handler;
use Swoft\Bean\Annotation\Inject;
use Swoft\Core\RequestContext;
use Swoft\Exception\RuntimeException;
use Exception;
use Swoft\Http\Message\Server\Request;
use Swoft\Http\Message\Server\Response;
use Swoft\Exception\BadMethodCallException;
use Swoft\Exception\ValidatorException;
use Swoft\Http\Server\Exception\BadRequestException;
use const OpenTracing\Formats\TEXT_MAP;

/**
 * the handler of global exception
 *
 * @ExceptionHandler()
 * @uses      Handler
 * @version   2018年01月14日
 * @author    stelin <phpcrazy@126.com>
 * @copyright Copyright 2010-2016 swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class SwoftExceptionHandler
{
    /**
     * @Handler(Exception::class)
     *
     * @param Response   $response
     * @param \Throwable $throwable
     *
     * @return Response
     */
    public function handlerException(Response $response, \Throwable $throwable)
    {
        $file      = $throwable->getFile();
        $line      = $throwable->getLine();
        $code      = $throwable->getCode();
        $exception = $throwable->getMessage();
        $this->traceException($exception,$file,$line);
        $data = ['msg' => $exception, 'file' => $file, 'line' => $line, 'code' => $code];
        App::error(json_encode($data));
        if($code == 1100)
            return $response->json(['code'=>1100,'message'=>'请先登录']);
        return $response->json($data);
    }

    /**
     * @Handler(OutOfBoundsException::class)
     *
     * @param Response   $response
     * @param \Throwable $throwable
     *
     * @return Response
     */
    public function handlerOutOfBoundsException(Response $response, \Throwable $throwable)
    {
        $file      = $throwable->getFile();
        $line      = $throwable->getLine();
        $code      = $throwable->getCode();
        $exception = $throwable->getMessage();
        $this->traceException($exception,$file,$line);
        $data = ['msg' => $exception, 'file' => $file, 'line' => $line, 'code' => $code];
        App::error(json_encode($data));
        if($exception == 'Requested claim is not configured')
            return $response->json(['code'=>1100,'message'=>'请先登录']);
        return $response->json($data);
    }

    /**
     * @Handler(InvalidArgumentException::class)
     *
     * @param Response   $response
     * @param \Throwable $throwable
     *
     * @return Response
     */
    public function handlerInvalidArgumentException(Response $response, \Throwable $throwable)
    {
        $file      = $throwable->getFile();
        $line      = $throwable->getLine();
        $code      = $throwable->getCode();
        $exception = $throwable->getMessage();
        $this->traceException($exception,$file,$line);
        $data = ['msg' => $exception, 'file' => $file, 'line' => $line, 'code' => $code];
        App::error(json_encode($data));
        if($exception == 'The JWT string must have two dots')
            return $response->json(['code'=>1100,'message'=>'请先登录']);
        return $response->json($data);
    }

    /**
     * @Handler(RuntimeException::class)
     *
     * @param Response   $response
     * @param \Throwable $throwable
     *
     * @return Response
     */
    public function handlerRuntimeException(Response $response, \Throwable $throwable)
    {
        $file      = $throwable->getFile();
        $code      = $throwable->getCode();
        $exception = $throwable->getMessage();
        $this->traceException($exception,$file);
        return $response->json([$exception, 'runtimeException']);
    }

    /**
     * @Handler(ValidatorException::class)
     *
     * @param Response   $response
     * @param \Throwable $throwable
     *
     * @return Response
     */
    public function handlerValidatorException(Response $response, \Throwable $throwable)
    {
        $exception = $throwable->getMessage();
        $this->traceException($exception);
        return $response->json(['message' => $exception]);
    }

    /**
     * @Handler(BadRequestException::class)
     *
     * @param Response   $response
     * @param \Throwable $throwable
     *
     * @return Response
     */
    public function handlerBadRequestException(Response $response, \Throwable $throwable)
    {
        $exception = $throwable->getMessage();
        $this->traceException($exception);
        return $response->json(['message' => $exception]);
    }

    /**
     * @Handler(BadMethodCallException::class)
     *
     * @param Request    $request
     * @param Response   $response
     * @param \Throwable $throwable
     *
     * @return Response
     */
    public function handlerViewException(Request $request, Response $response, \Throwable $throwable)
    {
        $name  = $throwable->getMessage(). $request->getUri()->getPath();
        $notes = [
            'New Generation of PHP Framework',
            'High Performance, Coroutine and Full Stack',
        ];
        $links = [
            [
                'name' => 'Home',
                'link' => 'http://www.swoft.org',
            ],
            [
                'name' => 'Documentation',
                'link' => 'http://doc.swoft.org',
            ],
            [
                'name' => 'Case',
                'link' => 'http://swoft.org/case',
            ],
            [
                'name' => 'Issue',
                'link' => 'https://github.com/swoft-cloud/swoft/issues',
            ],
            [
                'name' => 'GitHub',
                'link' => 'https://github.com/swoft-cloud/swoft',
            ],
        ];
        $data  = compact('name', 'notes', 'links');

        return view('exception/index', $data);
    }

    public function traceException($exception,$file='',$line=''){
        $span = \Swoft::getBean(TracerManager::class)->getServerSpan();
        if( $span ){
            GlobalTracer::get()->inject($span->getContext(), TEXT_MAP,
                RequestContext::getRequest()->getSwooleRequest()->header);
            $span->setTag('error',$exception);
            $span->setTag('file',$file);
            $span->setTag('line',$line);
            $span->finish();
            GlobalTracer::get()->flush();
        }
    }
}
```
#### 查看zipkin监控信息
```
docker run -d -p 9411:9411 openzipkin/zipkin
```
使用docker后 访问localhost:9411即可看到数据