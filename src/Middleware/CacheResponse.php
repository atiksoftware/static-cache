<?php

namespace Atiksoftware\StaticCache\Middleware;

use Closure;
use Atiksoftware\StaticCache\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
	/**
	 * The cache instance.
	 *
	 * @var \Atiksoftware\StaticCache\Cache
	 */
	protected $cache;

	/**
	 * Constructor.
	 *
	 * @var \Atiksoftware\StaticCache\Cache
	 */
	public function __construct(Cache $cache)
	{
		$this->cache = $cache;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next)
	{
		$response = $next($request);

		if ($this->shouldCache($request, $response)) {
			$this->cache->cache($request, $response);
		}

		return $response;
	}

	/**
	 * Determines whether the given request/response pair should be cached.
	 *
	 * @return bool
	 */
	protected function shouldCache(Request $request, Response $response)
	{
		return $request->isMethod('GET') && 200 === $response->getStatusCode();
	}
}
