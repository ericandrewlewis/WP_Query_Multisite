<?php

class WP_Query_Multisite {
	
	function __construct() {
		add_filter('query_vars', array($this, 'query_vars'));
		add_action('pre_get_posts', array($this, 'pre_get_posts'), 100);
		add_filter('posts_request', array($this, 'create_and_unionize_select_statements'), 10, 2);
		add_filter('posts_fields', array($this, 'add_site_ID_to_posts_fields'), 10, 2);
		add_action('the_post', array($this, 'switch_to_blog_while_in_loop'));
	}

	function query_vars($vars) {
		$vars[] = 'multisite';
		$vars[] = 'sites__not_in';
		$vars[] = 'sites__in';
		$vars[] = 'sites_to_query';
		return $vars;
	}
	
	function pre_get_posts($query) {
		if($query->get('multisite')) {

			global $wpdb;
			
			$site_IDs = $wpdb->get_col( "select blog_id from $wpdb->blogs" );

			if ( $query->get('sites__not_in') )
				foreach($site_IDs as $key => $site_ID )
					if (in_array($site_ID, $query->get('sites__not_in')) ) unset($site_IDs[$key]);
			
			if ( $query->get('sites__in') )
				foreach($site_IDs as $key => $site_ID )
					if ( ! in_array($site_ID, $query->get('sites__in')) ) 
						unset($site_IDs[$key]);

			$site_IDs = array_values($site_IDs);

			$query->set('sites_to_query', $site_IDs);
		}
	}

	function create_and_unionize_select_statements($sql, $query) {
		if($query->get('multisite')) {
			global $wpdb;

			$root_site_db_prefix = $wpdb->prefix;
			
			$page = $query->get('paged') ? $query->get('paged') : 1;
			$posts_per_page = $query->get('posts_per_page') ? $query->get('posts_per_page') : 10;

			$sites_to_query = $query->get('sites_to_query');

			foreach($sites_to_query as $key => $site_ID) {

				switch_to_blog($site_ID);

				$new_sql_select = str_replace($root_site_db_prefix, $wpdb->prefix, $sql);
				$new_sql_select = preg_replace("/ LIMIT ([0-9]+), ".$posts_per_page."/", "", $new_sql_select);
				$new_sql_select = str_replace("SQL_CALC_FOUND_ROWS ", "", $new_sql_select);
				$new_sql_select = str_replace("# AS site_ID", "'$site_ID' AS site_ID", $new_sql_select);
				$new_sql_select = preg_replace( '/ORDER BY ([A-Za-z0-9_.]+)/', "", $new_sql_select);
				$new_sql_select = str_replace(array("DESC", "ASC"), "", $new_sql_select);
				
				$new_sql_selects[] = $new_sql_select;
				restore_current_blog();

			}

			if ( $posts_per_page > 0 ) {
				$skip = ( $page * $posts_per_page ) - $posts_per_page;
				$limit = "LIMIT $skip, $posts_per_page";
			} else {
	            $limit = '';
	        }
			$orderby = "tables.post_date DESC";
			$sql = "SELECT SQL_CALC_FOUND_ROWS tables.* FROM ( " . implode(" UNION ", $new_sql_selects) . ") tables ORDER BY $orderby " . $limit;

		}
		return $sql;
	}
	
	function add_site_ID_to_posts_fields($sql, $query) {
		if($query->get('multisite')) {
			$sql_statements[] = $sql;
			$sql_statements[] = "# AS site_ID";
			$sql = implode(', ', $sql_statements);
		}
		return $sql;
	}
	
	function switch_to_blog_while_in_loop($post) {
		global $wp_query;
		if($wp_query->get('multisite')) { 
			global $blog_id;
			if($post->site_ID && $blog_id != $post->site_ID )
				switch_to_blog($post->site_ID);
			else
				restore_current_blog();
		}
	}
}

new WP_Query_Multisite();

?>
