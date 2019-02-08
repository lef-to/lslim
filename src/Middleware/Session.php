<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SessionHandlerInterface;
use Dflydev\FigCookies\FigResponseCookies as Cookies;
use Dflydev\FigCookies\SetCookie;

class Session
{
    /**
     * @param string $name
     * @param \SessionHandlerInterface|null $handler
     */
    public function __construct($name, SessionHandlerInterface $handler = null)
    {
        session_name($name);
        if ($handler !== null) {
            session_set_save_handler($handler);
        }
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');

        $id = '';
        $name = session_name();
        $cookies = $request->getCookieParams();

        if (isset($cookies[$name]) && !empty($cookies[$name])) {
            $id = $cookies[$name];
            session_id($id);
        }

        session_start();
        try {
            $response = $next($request, $response);
        } finally {
            if (session_status() == PHP_SESSION_ACTIVE) {
                $newId = session_id();
                if ($id != $newId) {
                    $cookie = Cookies::get($response, $name);

                    if (is_null($cookie->getValue())) {
                        $cookie = SetCookie::create($name)
                            ->withValue($newId)
                            ->withPath('/')
                            ->withHttpOnly(true);
            
                        $response = Cookies::set($response, $cookie);
                    }
                }
                session_write_close();
            }
        }

        return $response;
    }
}
