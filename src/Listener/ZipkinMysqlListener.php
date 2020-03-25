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
 * @Listener("Mysql")
 */
class ZipkinMysqlListener implements EventHandlerInterface
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

        $profileKey = $event->getParams()[0];
        $cid = Coroutine::tid();
        if ($event->getTarget() == 'start') {
            $sql = $event->getParams()[1];

            $tag = [
                'profileKey' => $profileKey,
                'db.statement' => $sql,
                'db.type'=>'mysql'
            ];


            $mysqlServer[$cid][$profileKey]['span'] = GlobalTracer::get()->startActiveSpan('mysql',
                [
                    'child_of' => \Swoft::getBean(TracerManager::class)->getServerSpan(),
                    'tags' => $tag
                ]);
            RequestContext::setContextDataByKey('mysqlSpans',$mysqlServer);
        } else {
            $mysqlSpans = RequestContext::getContextDataByKey('mysqlSpans');
            $mysqlSpans[$cid][$profileKey]['span']->close();
        }

    }
}