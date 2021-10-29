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

        $table_sql = "CREATE TABLE $table (
            ID bigint(20) unsigned NOT NULL auto_increment,
            created datetime NOT NULL default '0000-00-00 00:00:00',
            updated datetime NOT NULL default '0000-00-00 00:00:00',
            type varchar(15) NOT NULL default '',
            object_id bigint(20),
            html longtext,
            PRIMARY KEY (ID),
        ) $charset_collate;\n";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta( $table_sql );

        update_option( "wp_table_$table", true );
    }
}
