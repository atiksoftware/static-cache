<?php

namespace Atiksoftware\StaticCache;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class Cache
{
	/**
	 * The filesystem instance.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * The container instance.
	 *
	 * @var null|\Illuminate\Contracts\Container\Container
	 */
	protected $container;

	/**
	 * The directory in which to store the cached pages.
	 *
	 * @var null|string
	 */
	protected $cachePath;

	/**
	 * Constructor.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	public function __construct(Filesystem $files)
	{
		$this->files = $files;
	}

	/**
	 * Sets the container instance.
	 *
	 * @return $this
	 */
	public function setContainer(Container $container)
	{
		$this->container = $container;

		return $this;
	}

	/**
	 * Sets the directory in which to store the cached pages.
	 *
	 * @param string $path
	 */
	public function setCachePath($path): void
	{
		$this->cachePath = rtrim($path, '\/');
	}

	/**
	 * Gets the path to the cache directory.
	 *
	 * @param string ...$paths
	 *
	 * @throws \Exception
	 *
	 * @return string
	 */
	public function getCachePath()
	{
		$base = $this->cachePath ?: $this->getDefaultCachePath();

		if (null === $base) {
			throw new Exception('Cache path not set.');
		}

		return $this->join(array_merge([$base], \func_get_args()));
	}

	/**
	 * Join the given paths together by the system's separator.
	 *
	 * @param string[] $paths
	 *
	 * @return string
	 */
	protected function join(array $paths)
	{
		$trimmed = array_map(function ($path) {
			return trim($path, '/');
		}, $paths);

		return $this->matchRelativity(
			$paths[0],
			implode('/', array_filter($trimmed))
		);
	}

	/**
	 * Makes the target path absolute if the source path is also absolute.
	 *
	 * @param string $source
	 * @param string $target
	 *
	 * @return string
	 */
	protected function matchRelativity($source, $target)
	{
		return '/' === $source[0] ? '/' . $target : $target;
	}

	/**
	 * Caches the given response if we determine that it should be cache.
	 *
	 * @return $this
	 */
	public function cacheIfNeeded(Request $request, Response $response)
	{
		if ($this->shouldCache($request, $response)) {
			$this->cache($request, $response);
		}

		return $this;
	}

	/**
	 * Determines whether the given request/response pair should be cached.
	 *
	 * @return bool
	 */
	public function shouldCache(Request $request, Response $response)
	{
		return $request->isMethod('GET') && 200 === $response->getStatusCode();
	}

	/**
	 * Cache the response to a file.
	 */
	public function cache(Request $request, Response $response): void
	{
		list($path, $file) = $this->getDirectoryAndFileNames($request, $response);

		$this->files->makeDirectory($path, 0775, true, true);

		$this->files->put(
			$this->join([$path, $file]),
			$this->minifyHtml($response->getContent()),
			true
		);
	}

	/**
	 * Minify html can save half a file size.
	 *
	 * @param $html
	 *
	 * @return null|string|string[]
	 */
	private function minifyHtml($html)
	{
		$search = [
			'/(\n|^)(\x20+|\t)/',
			'/(\n|^)\/\/(.*?)(\n|$)/',
			'/\n/',
			'/\<\!--.*?-->/',
			'/(\x20+|\t)/', // Delete multispace (Without \n)
			'/\>\s+\</', // strip whitespaces between tags
			'/(\"|\')\s+\>/', // strip whitespaces between quotation ("') and end tags
			'/=\s+(\"|\')/', ]; // strip whitespaces between = "'

		$replace = [
			"\n",
			"\n",
			' ',
			'',
			' ',
			'><',
			'$1>',
			'=$1', ];

		return preg_replace($search, $replace, $html);
	}

	/**
	 * Remove the cached file for the given slug.
	 *
	 * @param string $slug
	 *
	 * @return bool
	 */
	public function forget($slug)
	{
		$deletedHtml = $this->files->delete($this->getCachePath($slug . '.html'));
		$deletedJson = $this->files->delete($this->getCachePath($slug . '.json'));
		$deletedXml = $this->files->delete($this->getCachePath($slug . '.xml'));

		return $deletedHtml || $deletedJson || $deletedXml;
	}

	/**
	 * Clear the full cache directory, or a subdirectory.
	 *
	 * @param  null|string
	 * @param null|mixed $pattern
	 *
	 * @return bool
	 */
	public function clear($pattern = null, Command $command = null)
	{
		$files = [];
		if (null === $pattern) {
			$files[] = $this->getCachePath();
		} else {
			$files = glob($this->getCachePath() . '/' . $pattern);
		}

		foreach ($files as $file) {
			if ($this->files->isDirectory($file)) {
				if (null !== $command) {
					$command->info('Clearing directory: ' . $file);
				}
				$this->files->deleteDirectory($file);
			} else {
				if (null !== $command) {
					$command->info('Clearing file: ' . $file);
				}
				$this->files->delete($file);
			}
		}
	}

	/**
	 * Get the names of the directory and file.
	 *
	 * @param \Illuminate\Http\Request  $request
	 * @param \Illuminate\Http\Response $response
	 *
	 * @return array
	 */
	protected function getDirectoryAndFileNames($request, $response)
	{
		$segments = explode('/', ltrim($request->getPathInfo(), '/'));
		$segments = array_filter($segments);

		$filename = $this->aliasFilename(array_pop($segments));
		$filename = $this->aliasQueryString($filename, $request);
		$extension = $this->guessFileExtension($response);

		$file = "{$filename}.{$extension}";

		return [$this->getCachePath(implode('/', $segments)), $file];
	}

	protected function aliasQueryString($filename, Request $request)
	{
		$current_url = $request->getRequestUri();
		$query = parse_url($current_url, PHP_URL_QUERY);
		if (null === $query) {
			return $filename;
		}
		// clear lfi and other bad stuff
		$query = preg_replace('/[^a-zA-Z0-9_\-\=\&]/', '', $query);

		return $filename . '[' . $query . ']';
	}

	/**
	 * Alias the filename if necessary.
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	protected function aliasFilename($filename)
	{
		return $filename ?: '__index';
	}

	/**
	 * Get the default path to the cache directory.
	 *
	 * @return null|string
	 */
	protected function getDefaultCachePath()
	{
		if ($this->container && $this->container->bound('path.public')) {
			return $this->container->make('path.public') . '/static-cache';
		}
	}

	/**
	 * Guess the correct file extension for the given response.
	 *
	 * Currently, only JSON and HTML are supported.
	 *
	 * @param mixed $response
	 *
	 * @return string
	 */
	protected function guessFileExtension($response)
	{
		$contentType = $response->headers->get('Content-Type');

		if ($response instanceof JsonResponse
			|| 'application/json' === $contentType
		) {
			return 'json';
		}

		if (\in_array($contentType, ['text/xml', 'application/xml'], true)) {
			return 'xml';
		}

		return 'html';
	}
}
