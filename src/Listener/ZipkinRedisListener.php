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


/**
 * http request
 *
 * @Listener("Redis")
 */
class ZipkinRedisListener implements EventHandlerInterface
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
            $method = $event->getParams()[0];
            $params = $event->getParams()[1];

            $tag = [
                'http.method' => $method,
                'params' => json_encode($params)
            ];


            $redisSpans[$cid]['span'] = GlobalTracer::get()->startActiveSpan('redis',
                [
                    'child_of' => \Swoft::getBean(TracerManager::class)->getServerSpan(),
                    'tags' => $tag
                ]);

            RequestContext::setContextDataByKey('redisSpans',$redisSpans);
        } else {
            $redisSpans = RequestContext::getContextDataByKey('redisSpans');
            $redisSpans[$cid]['span']->close();
        }

    }
}