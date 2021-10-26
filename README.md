# WordPress Prerender via AWS Lambda

Requires at least: PHP 7.1, WordPress 5.3.2

###    Description
WordPress plugin for rendering post/page content via AWS Lambda. Save rendered content to post meta field "prerender", which you can display before executing the frontend app.

Add the following constants to `wp-config.php`:

````
define( 'AWS_LAMBDA_WP_PRERENDER_KEY', '' );
define( 'AWS_LAMBDA_WP_PRERENDER_SECRET', '' );
define( 'AWS_LAMBDA_WP_PRERENDER_REGION', '' );
define( 'AWS_LAMBDA_WP_PRERENDER_FUNCTION', '' ); // default is "wordpress-prerender"
````

To star render content by AWS Lambda add hook:
````
add_filter( 'wp_enable_prerender', function () {
    return true;
} );
````

To change element to be rendered via AWS Lambda, default is `#app`:
````
add_filter( 'wp_prerender_element', function () {
    return 'YOUR_ELEMENT';
} );
````

To change the logic (when post is saved) of term prerender, use hook:

````
add_filter( 'wp_term_prerender', function ( $prerender, $post_id ): bool {
    ...
    
    return $prerender;
}, 10, 2 );
````

To change the logic (when post is saved) of archive prerender, use hook:

````
add_filter( 'wp_archive_prerender', function ( $prerender, $post_id ): bool {
    ...
    
    return $prerender;
}, 10, 2 );
````