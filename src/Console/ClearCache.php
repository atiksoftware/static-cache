<?php

namespace Atiksoftware\StaticCache\Console;

use Atiksoftware\StaticCache\Cache;
use Illuminate\Console\Command;

class ClearCache extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'page-cache:clear {slug? : URL slug of page/directory to delete} {--recursive}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Clear (all or part of) the page cache.';

	/**
	 * Execute the console command.
	 */
	public function handle(): void
	{
		$cache = $this->laravel->make(Cache::class);
		$recursive = $this->option('recursive');
		$slug = $this->argument('slug');

		if (!$slug) {
			$this->clear($cache);
		} elseif ($recursive) {
			$this->clear($cache, $slug);
		} else {
			$this->forget($cache, $slug);
		}
	}

	/**
	 * Remove the cached file for the given slug.
	 *
	 * @param string $slug
	 */
	public function forget(Cache $cache, $slug): void
	{
		if ($cache->forget($slug)) {
			$this->info("Page cache cleared for \"{$slug}\"");
		} else {
			$this->info("No page cache found for \"{$slug}\"");
		}
	}

	/**
	 * Clear the full page cache.
	 *
	 * @param null|string $path
	 */
	public function clear(Cache $cache, $path = null): void
	{
		if ($cache->clear($path)) {
			$this->info('Page cache cleared at ' . $cache->getCachePath($path));
		} else {
			$this->warn('Page cache not cleared at ' . $cache->getCachePath($path));
		}
	}
}
