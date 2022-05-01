<?php

namespace Atiksoftware\StaticCache;

use Illuminate\Support\ServiceProvider;
use Atiksoftware\StaticCache\Console\ClearCache;

class LaravelServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		$this->commands(ClearCache::class);

		$this->app->singleton(Cache::class, function () {
			$instance = new Cache($this->app->make('files'));

			return $instance->setContainer($this->app);
		});
	}
}
