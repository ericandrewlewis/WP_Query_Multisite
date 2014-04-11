<?php

class WP_Query_Multisite extends WP_Query{

	
	var $args;
	
	function __construct( $args = array() ) {
		$this->args = $args;
		$this->parse_multisite_args();
		$this->add_filters();
		$this->query($args);			  
		$this->remove_filters();

	}
	
	function parse_multisite_args() {
		global $wpdb;
		
		$site_IDs = $wpdb->get_col( "select blog_id from $wpdb->blogs" );

		if ( isset( $this->args['sites']['sites__not_in'] ) )
			foreach($site_IDs as $key => $site_ID )
				if (in_array($site_ID, $this->args['sites']['sites__not_in']) ) unset($site_IDs[$key]);
		
		if ( isset( $this->args['sites']['sites__in'] ) )
			foreach($site_IDs as $key => $site_ID )
				if ( ! in_array($site_ID, $this->args['sites']['sites__in']) ) 
					unset($site_IDs[$key]);

		
		$site_IDs = array_values($site_IDs);
		$this->sites_to_query = $site_IDs;
	}

	function add_filters() {
		
			add_filter('posts_request', array(&$this, 'create_and_unionize_select_statements') );
			add_filter('posts_fields', array(&$this, 'add_site_ID_to_posts_fields') );
			add_action('the_post', array(&$this, 'switch_to_blog_while_in_loop'));
			
	}
	function remove_filters() {
			remove_filter('posts_request', array(&$this, 'create_and_unionize_select_statements') );
			remove_filter('posts_fields', array(&$this, 'add_site_ID_to_posts_fields') );

	}

	function create_and_unionize_select_statements( $sql ) {
		global $wpdb;

		$root_site_db_prefix = $wpdb->prefix;
		
		$page = isset( $this->args['paged'] ) ? $this->args['paged'] : 1;
		$posts_per_page = isset( $this->args['posts_per_page'] ) ? $this->args['posts_per_page'] : 10;
		$s = ( isset( $this->args['s'] ) ) ? $this->args['s'] : false;

		foreach ($this->sites_to_query as $key => $site_ID) :

			switch_to_blog( $site_ID );

			$new_sql_select = str_replace($root_site_db_prefix, $wpdb->prefix, $sql);
			$new_sql_select = preg_replace("/ LIMIT ([0-9]+), ".$posts_per_page."/", "", $new_sql_select);
			$new_sql_select = str_replace("SQL_CALC_FOUND_ROWS ", "", $new_sql_select);
			$new_sql_select = str_replace("# AS site_ID", "'$site_ID' AS site_ID", $new_sql_select);
			$new_sql_select = preg_replace( '/ORDER BY ([A-Za-z0-9_.]+)/', "", $new_sql_select);
			$new_sql_select = str_replace(array("DESC", "ASC"), "", $new_sql_select);

			if( $s ){
				$new_sql_select = str_replace("LIKE '%{$s}%' , wp_posts.post_date", "", $new_sql_select); //main site id
				$new_sql_select = str_replace("LIKE '%{$s}%' , wp_{$site_ID}_posts.post_date", "", $new_sql_select);  // all other sites
			}
			
			$new_sql_selects[] = $new_sql_select;
			restore_current_blog();

		endforeach;

		if ( $posts_per_page > 0 ) {
			$skip = ( $page * $posts_per_page ) - $posts_per_page;
			$limit = "LIMIT $skip, $posts_per_page";
		} else {
            $limit = '';
        }
		$orderby = "tables.post_date DESC";
		$new_sql = "SELECT SQL_CALC_FOUND_ROWS tables.* FROM ( " . implode(" UNION ", $new_sql_selects) . ") tables ORDER BY $orderby " . $limit;

		return $new_sql;
	}
	
	function add_site_ID_to_posts_fields( $sql ) {
		$sql_statements[] = $sql;
		$sql_statements[] = "# AS site_ID";
		return implode(', ', $sql_statements);
	}
	
	function switch_to_blog_while_in_loop( $post ) {
		global $blog_id;
		if($post->site_ID && $blog_id != $post->site_ID )
			switch_to_blog($post->site_ID);
		else
			restore_current_blog();
	}
}

