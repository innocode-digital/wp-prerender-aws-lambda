<?php

namespace Innocode\Prerender;

/**
 * Class Db
 *
 * @package Innocode\Prerender
 */
class Db
{
    /**
     * @var string
     */
    private $table = 'prerender';

    /**
     * DB constructor.
     *
     * @param string $table_name
     */
    public function __construct( string $table = '' )
    {
        if( $table) {
            $this->set_table( $table );
        }

        if( ! $this->is_table_exists( $this->get_table() ) ) {
            $this->create_table( $this->get_table() );
        }
    }

    /**
     * @param string $table
     */
    private function set_table( string $table ): void
    {
        $this->table = $table;
    }

    /**
     * @return string
     */
    private function get_table(): string
    {
        return $this->table;
    }

    /**
     * @param string $table
     *
     * @return bool
     */
    private function is_table_exists( string $table ): bool
    {
        return ! ! get_option( "wp_table_$table" );
    }

    private function create_table( string $table )
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_sql = "CREATE TABLE $wpdb->prefix$table (
            ID bigint(20) unsigned NOT NULL auto_increment,
            created datetime NOT NULL default '0000-00-00 00:00:00',
            updated datetime NOT NULL default '0000-00-00 00:00:00',
            type varchar(15) NOT NULL default '',
            object_id bigint(20),
            html longtext,
            PRIMARY KEY (ID)
        ) $charset_collate;\n";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta( $table_sql );

        update_option( "wp_table_$table", true );
    }

    /**
     * @param int    $object_id
     * @param string $type
     *
     * @return string|null
     */
    public function get_entry( int $object_id = 0, string $type = '' )
    {
        if( ! $object_id && ! $type ) {
            return null;
        }

        global $wpdb;

        $table = $this->get_table();
        $query = "SELECT html FROM $wpdb->prefix$table";
        $where = false;

        if( $object_id ) {
            $where = true;
            $query .= $wpdb->prepare( " WHERE `object_id` = '%s'", $object_id );
        }

        if( $type ) {
            $query .= $where ? " AND" : " WHERE";
            $where = true;
            $query .= $wpdb->prepare( " `type` = '%s'", $type );
        }

        return $wpdb->get_var( $query );
    }

    /**
     * @param string $html
     * @param int    $object_id
     * @param string $type
     *
     * @return bool
     */
    public function save_entry( string $html, int $object_id = 0, string $type = '' ): bool
    {
        if( ! $object_id && ! $type ) {
            return false;
        }

        global $wpdb;

        $table = $this->get_table();

        if( $entry = $this->get_entry( $object_id, $type ) ) {
            $query = $wpdb->prepare(
                "UPDATE $wpdb->prefix$table SET `html` = %s, `updated` = %s",
                $html,
                date( 'Y-m-d H:i:s', time() )
            );
            $where = false;

            if( $object_id ) {
                $where = true;
                $query .= $wpdb->prepare( " WHERE `object_id` = '%s'", $object_id );
            }

            if( $type ) {
                $query .= $where ? " AND" : " WHERE";
                $where = true;
                $query .= $wpdb->prepare( " `type` = '%s'", $type );
            }
        } else {
            $query = $wpdb->prepare(
                "INSERT INTO $wpdb->prefix$table (created, updated, type, object_id, html) VALUES ( %s, %s, %s, %d, %s )",
                date( 'Y-m-d H:i:s', time() ),
                date( 'Y-m-d H:i:s', time() ),
                $type,
                $object_id,
                $html
            );
        }

        return !! $wpdb->query( $query );
    }

    /**
     * @param string $html
     * @param int    $object_id
     * @param string $type
     *
     * @return bool
     */
    public function clear_entry( int $object_id = 0, string $type = '' ): bool
    {
        if( ! $object_id && ! $type ) {
            return false;
        }

        return $this->save_entry( '', $object_id, $type );
    }
}
