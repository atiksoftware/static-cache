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
	protected $signature = 'static-cache:clear {slug? : URL slug of page/directory to delete. can be line posts*}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Clear (all or part of) the static page cache.';

	/**
	 * Execute the console command.
	 */
	public function handle(): void
	{
		$cache = $this->laravel->make(Cache::class);
		$slug = $this->argument('slug');

		$this->info("Clearing cache by search {$slug} ");

		$cache->clear($slug, $this);
	}
}
