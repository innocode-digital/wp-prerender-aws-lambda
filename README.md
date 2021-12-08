# AWS Lambda Prerender

### Description

Generates HTML for client-side rendered content via AWS Lambda. This plugin sends request
to [AWS Lambda Prerender](https://github.com/innocode-digital/aws-lambda-prerender) function
which renders content with [Puppeteer](https://github.com/puppeteer/puppeteer) and returns
HTML back through REST API callback.

### Install

- Preferable way is to use [Composer](https://getcomposer.org/):

    ````
    composer require innocode-digital/wp-prerender-aws-lambda
    ````

- Alternate way is to clone this repo to `wp-content/plugins/` or `wp-content/mu-plugins/`:

    ````
    cd wp-content/plugins/
    git clone git@github.com:innocode-digital/wp-prerender-aws-lambda.git
    cd wp-prerender-aws-lambda/
    composer install
    ````

If plugin was installed as regular plugin then activate **AWS Lambda Prerender** from Plugins page
or [WP-CLI](https://make.wordpress.org/cli/handbook/): `wp plugin activate wp-prerender-aws-lambda`.

### Configuration

Add the following constants to `wp-config.php`:

````
define( 'AWS_LAMBDA_PRERENDER_KEY', '' );
define( 'AWS_LAMBDA_PRERENDER_SECRET', '' );
define( 'AWS_LAMBDA_PRERENDER_REGION', '' ); // e.g. eu-west-1

define( 'AWS_LAMBDA_PRERENDER_FUNCTION', '' ); // Optional, default value is "prerender-production-render"
````

Create Lambda function on AWS. Expected default name is **prerender-production-render**
but you may use any other. There is a prepared function [AWS Lambda Prerender](https://github.com/innocode-digital/aws-lambda-prerender).

Used user should have `InvokeFunction` in policy.

---

````
define( 'AWS_LAMBDA_PRERENDER_DB_TABLE', '' ); // Optional, default value is "innocode_prerender"

define( 'AWS_LAMBDA_PRERENDER_QUERY_ARG', '' ); // Optional, default value is "innocode_prerender"
````

It's possible to change database table name where HTML will be stored and query argument
which is using when serverless function makes request to website.

### Usage

Plugin automatically generates HTML on Post/Page and Term save action with updating of
related content like Frontpage, but it's possible to control this behavior e.g. if you do not
want to update author's archive page on post save then use next filter:

````
add_filter( 'innocode_prerender_should_update_post_author', function (
    (bool) $should_update,
    int $object_id,
    $id
) : bool {
    // Do not update achive page of author with user id 5.
    if ( $id == 5 ) {
        return false;
    }

    return $should_update;
}, 10, 3 );
````

Also, plugin generates HTML "on the fly" when someone visits any of object with [type](#existing-types)
e.g. single post and if there is no content in database for current object in current
version then cron task will be scheduled to make new request to Lambda.

If theme does not support e.g. date archives then it's possible to disable them at all:

````
add_filter( 'innocode_prerender_types', function ( array $types ) : array {
    $keys = array_flip( $types );

    unset( $types[ $keys['date_archive'] ] );

    return $types;
} );
````

Also, it's possible to add custom type in addition to [existing](#existing-types):

````
add_filter( 'innocode_prerender_types', function ( array $types ) : array {
    $types[] = 'custom_type';

    return $types;
} );

add_filter( 'innocode_prerender_custom_id', function ( $object_id, $type, $id ) : int {
    if ( $type != 'custom_type' ) {
        return $object_id;
    }
    
    // Some magic to convert $id into $object_id etc.
    
    return $object_id;
}, 10, 3 );
````

But, in this case you should manually schedule cron task somewhere:

````
if ( function_exists( 'innocode_prerender' ) ) {
    innocode_prerender()
        ->get_prerender()
        ->schedule( 'custom_type', 43 ); // 'custom_type' is name of type and 43 some ID of object.
}
````

By default, plugin uses selector `#app` to grab content, i.e. that your client-side
application is wrapped in block with ID `app`:

````
<div id="app"></div>
````

If it's needed to change selector use next hook:

````
add_filter( 'innocode_prerender_selector', function () : string {
    return '#main';
} )
````

### Notes

#### Existing types

- `post`
- `term`
- `author`
- `frontpage`
- `post_type_archive`
- `date_archive`

#### Version

It was mentioned above that content has a version, and you may want to upgrade it at some
point, most obvious case when something has been changed in client-side rendered layout.
In this case it's needed to bump new version:

````
if ( function_exists( 'innocode_prerender' ) ) {
    innocode_prerender()
        ->get_db()
        ->get_html_version()
        ->bump();
}
````

But, you can just install [Flush Cache Buttons](https://github.com/innocode-digital/wp-flush-cache)
plugin which adds possibility to do this action by clicking on the button in admin panel.

#### Modify content during prerendering

Sometimes content which is prerendered should be different from what we get on client-side
e.g. you may need to exclude certain element. In this case use JavaScript global variable:

````
if (window.__INNOCODE_PRERENDER__) {
    // Only for prerendering.
} else {
    // Only for client-side.
}
````
