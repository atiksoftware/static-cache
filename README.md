# Laravel Page Cache


[![Latest Stable Version][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![License][ico-license]](LICENSE.txt)

This package allows you to easily cache responses as static files on disk for lightning fast page loads.

> **Note:** This package inspired by [JosephSilber/page-cache](https://github.com/JosephSilber/page-cache)

- [Introduction](#introduction)
- [Introduction](#introduction)
- [Installation](#installation)
  - [Service Provider](#service-provider)
  - [Middleware](#middleware)
  - [URL rewriting](#url-rewriting)
  - [Ignoring the cached files](#ignoring-the-cached-files)
- [Usage](#usage)
  - [Using the middleware](#using-the-middleware)
  - [Clearing the cache](#clearing-the-cache)
  - [Customizing what to cache](#customizing-what-to-cache)
- [License](#license)

---

## Introduction

While static site builders such as [Jekyll](https://jekyllrb.com/) and [Jigsaw](https://jigsaw.tighten.co/) are extremely popular these days, dynamic PHP sites still offer a lot of value even for a site that is mostly static. A proper PHP site allows you to easily add dynamic functionality wherever needed, and also means that there's no build step involved in pushing updates to the site.

That said, for truly static pages on a site there really is no reason to have to boot up a full PHP app just to serve a static page. Serving a simple HTML page from disk is infinitely faster and less taxing on the server.

The solution? Full page caching.

Using the middleware included in this package, you can selectively cache the response to disk for any given request. Subsequent calls to the same page will be served directly as a static HTML page!

## Installation

Install the `static-cache` package with composer:

```
$ composer require atiksoftware/static-cache
```

### Service Provider

> **Note**: If you're using Laravel 5.5+, the service provider will be registered automatically. You can simply skip this step entirely.

Open `config/app.php` and add a new item to the `providers` array:

```php
Atiksoftware\StaticCache\LaravelServiceProvider::class,
```

### Middleware

Open `app/Http/Kernel.php` and add a new item to the `web` middleware group:

```php
protected $middlewareGroups = [
    'web' => [
        \Atiksoftware\StaticCache\Middleware\CacheResponse::class,
        /* ... keep the existing middleware here */
    ],
];
```

The middleware is smart enough to only cache responses with a 200 HTTP status code, and only for GET requests.

If you want to selectively cache only specific requests to your site, you should instead add a new mapping to the `routeMiddleware` array:

```php
protected $routeMiddleware = [
    'static-cache' => \Atiksoftware\StaticCache\Middleware\CacheResponse::class,
    /* ... keep the existing mappings here */
];
```

Once registered, you can then [use this middleware on individual routes](#using-the-middleware).

### URL rewriting

In order to serve the static files directly once they've been cached, you need to properly configure your web server to check for those static files.

- **For nginx:**

    Update your `location` block's `try_files` directive to include a check in the `static-cache` directory:

    ```nginxconf
    location = / {
        try_files /static-cache/__index.html /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /static-cache/$uri.html /static-cache/$uri.json /static-cache/$uri.xml /index.php?$query_string;
    }
    ```

- **For apache:**

    Open `public/.htaccess` and add the following before the block labeled `Handle Front Controller`:

    ```apacheconf
    # Serve Cached Page If Available...
    RewriteCond %{REQUEST_URI} ^/?$
    RewriteCond %{DOCUMENT_ROOT}/static-cache/__index.html -f
    RewriteRule .? static-cache/__index.html [L]
    
    RewriteCond %{DOCUMENT_ROOT}/static-cache%{REQUEST_URI}.html -f
    RewriteRule . static-cache%{REQUEST_URI}.html [L]
    RewriteCond %{DOCUMENT_ROOT}/static-cache%{REQUEST_URI}[%{QUERY_STRING}].html -f
    RewriteRule . static-cache%{REQUEST_URI}[%{QUERY_STRING}].html [L]

    RewriteCond %{DOCUMENT_ROOT}/static-cache%{REQUEST_URI}.json -f
    RewriteRule . static-cache%{REQUEST_URI}.json [L]
    RewriteCond %{DOCUMENT_ROOT}/static-cache%{REQUEST_URI}[%{QUERY_STRING}].json -f
    RewriteRule . static-cache%{REQUEST_URI}[%{QUERY_STRING}].json [L] 

    RewriteCond %{DOCUMENT_ROOT}/static-cache%{REQUEST_URI}.xml -f
    RewriteRule . static-cache%{REQUEST_URI}.xml [L]
    RewriteCond %{DOCUMENT_ROOT}/static-cache%{REQUEST_URI}[%{QUERY_STRING}].xml -f
    RewriteRule . static-cache%{REQUEST_URI}[%{QUERY_STRING}].xml [L]
    ```

### Ignoring the cached files

To make sure you don't commit your locally cached files to your git repository, add this line to your `.gitignore` file:

```
/public/static-cache
```

## Usage

### Using the middleware

> **Note:** If you've added the middleware to the global `web` group, then all successful GET requests will automatically be cached. No need to put the middleware again directly on the route.
>
> If you instead registered it as a route middleware, you should use the middleware on whichever routes you want to be cached.

To cache the response of a given request, use the `static-cache` middleware:

```php
Route::middleware('static-cache')->get('/haberler', [\App\Http\Controllers\App\PostController::class, 'index'])->name('posts');
Route::middleware('static-cache')->get('/haberler/kategori/{category:slug}', [\App\Http\Controllers\App\PostController::class, 'by_category'])->scopeBindings()->name('posts.by_category');
Route::middleware('static-cache')->get('/haberler/ilce/{district:slug}', [\App\Http\Controllers\App\PostController::class, 'by_district'])->scopeBindings()->name('posts.by_district');
Route::middleware('static-cache')->get('/haberler/yazar/{user:slug}', [\App\Http\Controllers\App\PostController::class, 'by_user'])->scopeBindings()->name('posts.by_user');
Route::middleware('static-cache')->get('/haber/{post:slug}', [\App\Http\Controllers\App\PostController::class, 'view'])->scopeBindings()->name('post');
Route::middleware('static-cache')->get('/haberler/{tag:name}', [\App\Http\Controllers\App\PostController::class, 'by_tags'])->name('posts.by_tags');

```

Every post will now be cached to a file under the `public/static-cache` directory, closely matching the URL structure of the request. All subsequent  requests for this post will be served directly from disk, never even hitting your app!

### Clearing the cache

Since the responses are cached to disk as static files, any updates to those pages in your app will not be reflected on your site. To update pages on your site, you should clear the cache with the following command:

```
php artisan static-cache:clear
```

As a rule of thumb, it's good practice to add this to your deployment script. That way, whenever you push an update to your site the page cache will automatically be cleared.

If you're using [Forge](https://forge.laravel.com)'s Quick Deploy feature, you should add this line to the end of your Deploy Script. This'll ensure that the cache is cleared whenever you push an update to your site.

You may optionally pass a URL slug to the command, to only delete the cache for a specific page:

```
php artisan static-cache:clear posts/first-post
php artisan static-cache:clear posts*
php artisan static-cache:clear posts/first*
``` 
 


### Customizing what to cache

By default, all GET requests with a 200 HTTP response code are cached. If you want to change that, create your own middleware that extends the package's base middleware, and override the `shouldCache` method with your own logic.

1. Run the `make:middleware` Artisan command to create your middleware file:

    ```
    php artisan make:middleware CacheResponse
    ```

2. Replace the contents of the file at `app/Http/Middleware/CacheResponse.php` with this:

    ```php
    <?php

    namespace App\Http\Middleware;

    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Atiksoftware\StaticCache\Middleware\CacheResponse as BaseCacheResponse;

    class CacheResponse extends BaseCacheResponse
    {
        protected function shouldCache(Request $request, Response $response)
        {
            // In this example, we don't ever want to cache pages if the
            // URL contains a query string. So we first check for it,
            // then defer back up to the parent's default checks.
            if ($request->getQueryString()) {
                return false;
            }

            return parent::shouldCache($request, $response);
        }
    }
    ```

3. Finally, update the middleware references in your `app/Http/Kernel.php` file, to point to your own middleware.

## License

The Page Cache package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

[ico-downloads]: https://poser.pugx.org/atiksoftware/static-cache/downloads
[ico-license]: https://poser.pugx.org/atiksoftware/static-cache/license
[ico-travis]: https://travis-ci.org/Josephatiksoftware/static-cache.svg
[ico-version]: https://poser.pugx.org/atiksoftware/static-cache/v/stable

[link-downloads]: https://packagist.org/packages/atiksoftware/static-cache
[link-packagist]: https://packagist.org/packages/atiksoftware/static-cache
[link-travis]: https://travis-ci.org/Josephatiksoftware/static-cache
