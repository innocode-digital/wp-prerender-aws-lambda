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
     * @param string $table
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
        return ( bool ) get_option( "innocode_table_$table" );
    }

    /**
     * @param string $table
     */
    private function create_table( string $table )
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_sql = "CREATE TABLE $wpdb->prefix$table (
            ID bigint(20) unsigned NOT NULL auto_increment,
            created datetime NOT NULL default '0000-00-00 00:00:00',
            updated datetime NOT NULL default '0000-00-00 00:00:00',
            type varchar(25) NOT NULL default '',
            object_id bigint(20) NOT NULL default 0,
            html longtext,
            PRIMARY KEY (ID),
            KEY (type, object_id)
        ) $charset_collate;\n";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta( $table_sql );

        update_option( "innocode_table_$table", true );
    }

    /**
     * @param string $type
     * @param int    $object_id
     *
     * @return array|object|void|null
     */
    private function get_entry( string $type, int $object_id = 0 )
    {
        global $wpdb;

        $table = $this->get_table();
        $query =  $wpdb->prepare(
            "SELECT * FROM $wpdb->prefix$table WHERE `type` = '%s' AND `object_id` = '%d '",
            $type,
            $object_id
        );

        return $wpdb->get_row( $query );
    }

    /**
     * @param string $html
     * @param string $type
     * @param int    $object_id
     *
     * @return int
     */
    private function create_entry( string $html, string $type, int $object_id = 0 ): int
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . $this->get_table(),
            [
                'created'   => date( 'Y-m-d H:i:s', time() ),
                'updated'   => date( 'Y-m-d H:i:s', time() ),
                'type'      => $type,
                'object_id' => $object_id,
                'html'      => $html
            ],
            [ '%s', '%s', '%s', '%d', '%s' ]
        );

        return $wpdb->insert_id;
    }

    /**
     * @param string $html
     * @param string $type
     * @param int    $object_id
     *
     * @return mixed
     */
    private function update_entry( string $html, string $type, int $object_id = 0 )
    {
        global $wpdb;

        $where = [ 'type'  => $type ];
        $where_format = [ '%s' ];

        if( $object_id ) {
            $where[ 'object_id' ] = $object_id;
            $where_format[] = '%d';
        }

        return $wpdb->update(
            $wpdb->prefix . $this->get_table(),
            [
                'updated'   => date( 'Y-m-d H:i:s', time() ),
                'html'      => $html
            ],
            $where,
            [' %s', '%s' ],
            $where_format
        );
    }

    /**
     * @param string $html
     * @param string $type
     * @param int    $object_id
     *
     * @return false|int
     */
    public function save_entry( string $html, string $type, int $object_id = 0 )
    {
        if( ! Tools::check_type( $type ) ) {
            return false;
        }

        return $this->get_entry( $type, $object_id )
            ? $this->update_entry( $html, $type, $object_id )
            : $this->create_entry( $html, $type, $object_id ) ;
    }

    /**
     * @param string $type
     * @param int    $object_id
     *
     * @return false|int
     */
    public function clear_entry( string $type, int $object_id = 0 )
    {
        return $this->save_entry( '', $type, $object_id );
    }

    /**
     * @param int    $object_id
     * @param string $type
     *
     * @return bool
     */
    public function delete_entry( string $type, int $object_id = 0 ): bool
    {
        if( ! Tools::check_type( $type ) ) {
            return false;
        }

        global $wpdb;

        $where = [ 'type'  => $type ];
        $where_format = [ '%s' ];

        if( $object_id ) {
            $where[ 'object_id' ] = $object_id;
            $where_format[] = '%d';
        }

        return (bool) $wpdb->delete(
            $wpdb->prefix . $this->get_table(),
            $where,
            $where_format
        );
    }

    /**
     * @param string $type
     * @param int    $object_id
     *
     * @return string
     */
    public function get_html( string $type, int $object_id = 0 ): string
    {
        if( ! Tools::check_type( $type ) ) {
            return '';
        }

        return $this->get_entry( $type, $object_id )->html ?? '';
    }
}
