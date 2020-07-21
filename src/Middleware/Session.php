<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use SessionHandlerInterface;
use Dflydev\FigCookies\FigResponseCookies as Cookies;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\Modifier\SameSite;
use Psr\Http\Server\RequestHandlerInterface;

class Session implements MiddlewareInterface
{
    /**
     * @var string
     */
    protected $path = '/';

    /**
     * @var bool
     */
    protected $httpOnly = true;

    /**
     * @var int|string|\DateTimeInterface|null
     */
    protected $expires = null;

    /**
     * @var string|null
     */
    protected $sameSite = 'strict';

    /**
     * @param string $name
     * @param \SessionHandlerInterface|null $handler
     */
    public function __construct($name, SessionHandlerInterface $handler = null)
    {
        session_name($name);
        if ($handler !== null) {
            session_set_save_handler($handler, false);
        }
    }

    public function setPath($path): self
    {
        $this->path = $path;
        return $this;
    }

    public function setHttpOnly($httpOnly): self
    {
        $this->httpOnly = $httpOnly;
        return $this;
    }

    public function setExpires($expires): self
    {
        $this->expires = $expires;
        return $this;
    }

    public function setSamesite($sameSite): self
    {
        $this->sameSite = $sameSite;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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
            $response = $handler->handle($request);
            if (session_status() == PHP_SESSION_ACTIVE) {
                $newId = session_id();
                if ($id != $newId) {
                    $cookie = Cookies::get($response, $name);

                    if (is_null($cookie->getValue())) {
                        $cookie = SetCookie::create($name)
                            ->withValue($newId)
                            ->withPath($this->path)
                            ->withExpires($this->expires)
                            ->withHttpOnly($this->httpOnly);

                        if ($this->sameSite === null) {
                            $cookie = $cookie->withoutSameSite();
                        } else {
                            $cookie = $cookie->withSameSite(SameSite::fromString($this->sameSite));
                        }

                        $response = Cookies::set($response, $cookie);
                    }
                }
            }

            return $response;
        } finally {
            if (session_status() == PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }
}
