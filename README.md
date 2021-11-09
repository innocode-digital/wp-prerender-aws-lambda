# WordPress Prerender via AWS Lambda

Requires at least: PHP 7.1, WordPress 5.3.2

###    Description
WordPress plugin for rendering post/page content via AWS Lambda.

Add the following constants to `wp-config.php`:

````
define( 'AWS_LAMBDA_WP_PRERENDER_KEY', '' );
define( 'AWS_LAMBDA_WP_PRERENDER_SECRET', '' );
define( 'AWS_LAMBDA_WP_PRERENDER_REGION', '' );
define( 'AWS_LAMBDA_WP_PRERENDER_FUNCTION', '' ); // default is "wordpress-prerender"
````

To change element to be rendered via AWS Lambda, default is `#app`:
````
add_filter( 'innocode_prerender_element', function () {
    return 'YOUR_ELEMENT';
} );
````

To get prerender content please use `innocode_wp_prerender_aws_lambda` function:

````
innocode_wp_prerender_aws_lambda()->get_html( $type, $id );
````

where `type` can be `frontpage`, `post`, `term`, `{$post_type}_archive`. Parameter `$id` is required only for `post` and `term` types