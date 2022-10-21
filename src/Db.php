<?php

namespace Innocode\Prerender;

use Innocode\Version\Version;

class Db
{
    const VERSION = '1.0.0';

    /**
     * @var string
     */
    protected $table;
    /**
     * @var Version
     */
    protected $version;
    /**
     * @var Version
     */
    protected $html_version;

    public function __construct()
    {
        $this->version = new Version();
        $this->html_version = new Version();
    }

    /**
     * @param string $table
     *
     * @return void
     */
    public function set_table( string $table ) : void
    {
        $this->table = $table;
    }

    /**
     * @return string
     */
    public function get_table() : string
    {
        return $this->table;
    }

    /**
     * @return Version
     */
    public function get_version() : Version
    {
        return $this->version;
    }

    /**
     * @return Version
     */
    public function get_html_version() : Version
    {
        return $this->html_version;
    }

    /**
     * @return void
     */
    public function init() : void
    {
        $table = $this->get_table();

        $version = $this->get_version();
        $version->set_option( "{$table}_db_version" );

        if ( null === $version() ) {
            $this->create_table();
        }

        $html_version = $this->get_html_version();
        $html_version->set_option( "{$table}_html_version" );
        $html_version->init();
    }

    /**
     * @return void
     */
    protected function create_table() : void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $query = "CREATE TABLE $wpdb->prefix{$this->get_table()} (
            ID bigint(20) unsigned NOT NULL auto_increment,
            created datetime NOT NULL default '0000-00-00 00:00:00',
            updated datetime NOT NULL default '0000-00-00 00:00:00',
            type varchar(50) NOT NULL default '',
            object_id bigint(20) NOT NULL default 0,
            html longtext,
            version varchar(32) NOT NULL default '',
            PRIMARY KEY (ID),
            KEY (`type`, `object_id`)
        ) $charset_collate;\n";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $query );

        $this->get_version()->update( static::VERSION );
    }

    /**
     * @param string $html
     * @param string $version
     * @param string $type
     * @param int    $object_id
     *
     * @return int
     */
    public function create_entry( string $html, string $version, string $type, int $object_id = 0 ) : int
    {
        global $wpdb;

        $now = current_time( 'mysql' );
        $created = (bool) $wpdb->insert(
            $wpdb->prefix . $this->get_table(),
            [
                'created'   => $now,
                'updated'   => $now,
                'type'      => $type,
                'object_id' => $object_id,
                'html'      => $html,
                'version'   => $version,
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( $created ) {
            wp_cache_delete( "$type:$object_id", 'innocode_prerender' );
        }

        return $wpdb->insert_id;
    }

    /**
     * @param string $type
     * @param int    $object_id
     *
     * @return Entry|null
     */
    public function get_entry( string $type, int $object_id = 0 ) : ?Entry
    {
        global $wpdb;

        $cache_key = "$type:$object_id";

        if ( false !== ( $data = wp_cache_get( $cache_key, 'innocode_prerender' ) ) ) {
            return new Entry( $data );
        }

        $query = $wpdb->prepare(
            "SELECT * FROM $wpdb->prefix{$this->get_table()} WHERE `type` = %s AND `object_id` = %d",
            $type,
            $object_id
        );
        $data = $wpdb->get_row( $query, ARRAY_A );

        if ( null === $data ) {
            return null;
        }

        wp_cache_set( $cache_key, $data, 'innocode_prerender' );

        return new Entry( $data );
    }

    /**
     * @param string $html
     * @param string $version
     * @param string $type
     * @param int    $object_id
     *
     * @return bool
     */
    public function update_entry( string $html, string $version, string $type, int $object_id = 0 ) : bool
    {
        global $wpdb;

        $updated = (bool) $wpdb->update(
            $wpdb->prefix . $this->get_table(),
            [
                'updated' => current_time( 'mysql' ),
                'html'    => $html,
                'version' => $version,
            ],
            [ 'type' => $type, 'object_id' => $object_id ],
            [' %s', '%s', '%s' ],
            [ '%s', '%d' ]
        );

        if ( $updated ) {
            wp_cache_delete( "$type:$object_id", 'innocode_prerender' );
        }

        return $updated;
    }

    /**
     * @param int    $object_id
     * @param string $type
     *
     * @return bool
     */
    public function delete_entry( string $type, int $object_id = 0 ) : bool
    {
        global $wpdb;

        $deleted = (bool) $wpdb->delete(
            $wpdb->prefix . $this->get_table(),
            [ 'type' => $type, 'object_id' => $object_id ],
            [ '%s', '%d' ]
        );

        if ( $deleted ) {
            wp_cache_delete( "$type:$object_id", 'innocode_prerender' );
        }

        return $deleted;
    }

    /**
     * @param string $html
     * @param string $version
     * @param string $type
     * @param int    $object_id
     *
     * @return bool|int
     */
    public function save_entry( string $html, string $version, string $type, int $object_id = 0 )
    {
        return null !== $this->get_entry( $type, $object_id )
            ? $this->update_entry( $html, $version, $type, $object_id )
            : $this->create_entry( $html, $version, $type, $object_id );
    }

    /**
     * @param string $type
     * @param int    $object_id
     *
     * @return bool|int
     */
    public function clear_entry( string $type, int $object_id = 0 )
    {
        return $this->save_entry( '', '', $type, $object_id );
    }

    /**
     * @return void
     */
    public function drop_table() : void
    {
        global $wpdb;

        $wpdb->query( "DROP TABLE IF EXISTS $wpdb->prefix{$this->get_table()}" );

        $this->get_version()->delete();
        $this->get_html_version()->delete();
    }
}
