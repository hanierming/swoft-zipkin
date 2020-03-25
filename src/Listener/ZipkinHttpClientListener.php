<?php
declare(strict_types=1);

namespace Hanier\Zipkin\Listener;


use Hanier\Zipkin\Manager\TracerManager;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Listener;
use Swoft\Core\Coroutine;
use Swoft\Core\RequestContext;
use Swoft\Event\EventHandlerInterface;
use Swoft\Event\EventInterface;
use Swoft\Exception\Exception;
use Psr\Http\Message;


/**
 * http request
 *
 * @Listener("HttpClient")
 */
class ZipkinHttpClientListener implements EventHandlerInterface
{

    protected $profiles = [];


    /**
     * @param EventInterface $event
     * @throws Exception
     */
    public function handle(EventInterface $event)
    {
        if (empty(\Swoft::getBean(TracerManager::class)->getServerSpan()))
        {
            return;
        }

        $cid = Coroutine::tid();

        if ($event->getTarget() == 'start') {
            /** @var Message\RequestInterface $request */
            $request = $event->getParams()[0];
            $options = $event->getParams()[1];
            $uri = $request->getUri();


            $tags = [
                'http.method' => $request->getMethod(),
                'host' => $uri->getHost(),
                'port' => $uri->getPort(),
                'path' => $uri->getPath(),
                'query' => $uri->getQuery(),
                'headers' => !empty($request->getHeaders()) ? json_encode($request->getHeaders()) : ''
            ];

            if ($request->getMethod() != 'GET')
            {
                $tags['body'] = $options['body'];
            }

            $httpServer[$cid]['span'] = GlobalTracer::get()->startActiveSpan('http-server:'.$uri->getPath(),
                [
                    'child_of' => \Swoft::getBean(TracerManager::class)->getServerSpan(),
                    'tags' => $tags
                ]);
            RequestContext::setContextDataByKey('httpServerSpans',$httpServer);
        } elseif($event->getTarget() == 'error'){
            $serverSpans = RequestContext::getContextDataByKey('httpServerSpans');
            $serverSpans[$cid]['span']->getSpan()->setTag('error',$event->getParams()[0]);
        } elseif($event->getTarget() == 'status_code'){
            $serverSpans = RequestContext::getContextDataByKey('httpServerSpans');
            $serverSpans[$cid]['span']->getSpan()->setTag('http.status_code',$event->getParams()[0]);
            if($event->getParams()[0] != 200)
                $serverSpans[$cid]['span']->getSpan()->setTag('error','http_status_code:'.$event->getParams()[0]);
        } else {
            $serverSpans = RequestContext::getContextDataByKey('httpServerSpans');
            $serverSpans[$cid]['span']->close();
        }

    }
}