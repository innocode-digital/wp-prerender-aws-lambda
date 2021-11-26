<?php

namespace Innocode\Prerender;

/**
 * Class Db
 *
 * @package Innocode\Prerender
 */
class Db
{
    const VERSION = '1.0.0';

    /**
     * @var string
     */
    protected $table = 'innocode_prerender';

    /**
     * @param string $table
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
     * @return string|null
     */
    public function get_version() : ?string
    {
        return get_option( 'innocode_prerender_db_version', null );
    }

    public function init()
    {
        if ( null === $this->get_version() ) {
            $this->create_table();
        }
    }

    protected function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $query = "CREATE TABLE $wpdb->prefix{$this->get_table()} (
            ID bigint(20) unsigned NOT NULL auto_increment,
            created datetime NOT NULL default '0000-00-00 00:00:00',
            updated datetime NOT NULL default '0000-00-00 00:00:00',
            type varchar(25) NOT NULL default '',
            object_id bigint(20) NOT NULL default 0,
            html longtext,
            PRIMARY KEY (ID),
            KEY (`type`, `object_id`)
        ) $charset_collate;\n";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $query );

        update_option( 'innocode_prerender_db_version', static::VERSION );
    }

    /**
     * @param string $html
     * @param string $type
     * @param int    $object_id
     *
     * @return int
     */
    public function create_entry( string $html, string $type, int $object_id = 0 ) : int
    {
        global $wpdb;

        $now = current_time( 'mysql' );
        $wpdb->insert(
            $wpdb->prefix . $this->get_table(),
            [
                'created'   => $now,
                'updated'   => $now,
                'type'      => $type,
                'object_id' => $object_id,
                'html'      => $html
            ],
            [ '%s', '%s', '%s', '%d', '%s' ]
        );

        return $wpdb->insert_id;
    }

    /**
     * @param string $type
     * @param int    $object_id
     *
     * @return array|null
     */
    public function get_entry( string $type, int $object_id = 0 ) : ?array
    {
        global $wpdb;

        $cache_key = "$type:$object_id";

        if ( false !== ( $entry = wp_cache_get( $cache_key, 'innocode_prerender' ) ) ) {
            return $entry;
        }

        $query = $wpdb->prepare(
            "SELECT * FROM $wpdb->prefix{$this->get_table()} WHERE `type` = %s AND `object_id` = %d",
            $type,
            $object_id
        );
        $entry = $wpdb->get_row( $query, ARRAY_A );

        wp_cache_set( $cache_key, $entry, 'innocode_prerender' );

        return $entry;
    }

    /**
     * @param string $html
     * @param string $type
     * @param int    $object_id
     *
     * @return bool
     */
    public function update_entry( string $html, string $type, int $object_id = 0 ) : bool
    {
        global $wpdb;

        $updated = (bool) $wpdb->update(
            $wpdb->prefix . $this->get_table(),
            [
                'updated' => current_time( 'mysql' ),
                'html'    => $html,
            ],
            [ 'type' => $type, 'object_id' => $object_id ],
            [' %s', '%s' ],
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
     * @param string $type
     * @param int    $object_id
     *
     * @return bool|int
     */
    public function save_entry( string $html, string $type, int $object_id = 0 )
    {
        return null !== $this->get_entry( $type, $object_id )
            ? $this->update_entry( $html, $type, $object_id )
            : $this->create_entry( $html, $type, $object_id );
    }

    /**
     * @param string $type
     * @param int    $object_id
     *
     * @return bool|int
     */
    public function clear_entry( string $type, int $object_id = 0 )
    {
        return $this->save_entry( '', $type, $object_id );
    }
}
