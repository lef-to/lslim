<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Csrf\Guard as Csrf;
use LSlim\Middleware\CsrfWrapper;

class CsrfProvider implements ServiceProviderInterface
{
    /**
     * @var \Psr\Http\Message\ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @var array
     */
    protected $option;

    public function __construct(ResponseFactoryInterface $responseFactory, array $option = [])
    {
        $this->responseFactory = $responseFactory;
        $this->option = $option;
    }

    public function register(Container $container)
    {
        $responseFactory = $this->responseFactory;
        $option = $this->option;

        $container['csrf'] = static function (Container $c) use ($responseFactory, $option) {
            $csrf = new Csrf($responseFactory);

            if (isset($option['storage_limit'])) {
                $csrf->setStorageLimit($option['storage_limit']);
            }
            if (isset($option['failure_callable'])) {
                $csrf->setFailureHandler($option['failure_callable']);
            } else {
                $handler = function (
                    ServerRequestInterface $request,
                    RequestHandlerInterface $handler
                ) use (
                    $responseFactory,
                    $c
                ) {
                    $uri = $request->getUri();

                    if (isset($c['logger'])) {
                        $c['logger']->error('csrf check failuer.', [ 'path' => $uri->getPath() ]);
                    }

                    return $responseFactory->createResponse()
                        ->withHeader('Location', (string)$uri)
                        ->withStatus(302);
                };

                $csrf->setFailureHandler($handler);
            }

            return new CsrfWrapper($csrf);
        };
    }
}
