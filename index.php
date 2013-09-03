<?php

class WP_Query_Multisite {

	function __construct() {
		add_filter('query_vars', array($this, 'query_vars'));
		add_action('pre_get_posts', array($this, 'pre_get_posts'), 100);
		add_filter('posts_clauses', array($this, 'posts_clauses'), 10, 2);
		add_filter('posts_request', array($this, 'posts_request'), 10, 2);
		add_action('the_post', array($this, 'the_post'));
		add_action('loop_end', array($this, 'loop_end'));
	}

	function query_vars($vars) {
		$vars[] = 'multisite';
		$vars[] = 'sites__not_in';
		$vars[] = 'sites__in';
		return $vars;
	}
	
	function pre_get_posts($query) {
		if($query->get('multisite')) {

			global $wpdb, $blog_id;

			$this->loop_end = false;
			$this->blog_id = $blog_id;

			$site_IDs = $wpdb->get_col( "select blog_id from $wpdb->blogs" );

			if ( $query->get('sites__not_in') )
				foreach($site_IDs as $key => $site_ID )
					if (in_array($site_ID, $query->get('sites__not_in')) ) unset($site_IDs[$key]);
			
			if ( $query->get('sites__in') )
				foreach($site_IDs as $key => $site_ID )
					if ( ! in_array($site_ID, $query->get('sites__in')) ) 
						unset($site_IDs[$key]);

			$site_IDs = array_values($site_IDs);

			$this->sites_to_query = $site_IDs;
		}
	}

	function posts_clauses($clauses, $query) {
		if($query->get('multisite')) {
			global $wpdb;

			// Orderby for tables (not wp_posts)
			$clauses['orderby'] = str_replace($wpdb->posts, 'tables', $clauses['orderby']);

			// State new selection to replace wp_posts on posts_request
			$this->ms_select = array();

			$root_site_db_prefix = $wpdb->prefix;
			foreach($this->sites_to_query as $site_ID) {

				switch_to_blog($site_ID);

				$ms_select = str_replace($root_site_db_prefix, $wpdb->prefix, $clauses['where']);
				$ms_select = " SELECT $wpdb->posts.*, '$site_ID' as site_ID FROM $wpdb->posts WHERE 1=1 $ms_select ";

				$this->ms_select[] = $ms_select;

				restore_current_blog();

			}

			// Clear where to populate on posts_request;
			$clauses['where'] = '';

		}
		return $clauses;
	}

	function posts_request($sql, $query) {

		if($query->get('multisite')) {

			global $wpdb;

			// Clean up remanescent WHERE request
			$sql = str_replace('WHERE 1=1', '', $sql);

			// Multisite request
			$sql = str_replace("$wpdb->posts.* FROM $wpdb->posts", 'tables.* FROM ( ' . implode(" UNION ", $this->ms_select) . ' ) tables', $sql);

		}

		return $sql;
	}
	
	function the_post($post) {
		global $blog_id;

		if(!$this->loop_end && $post->site_ID && $blog_id !== $post->site_ID) {
			switch_to_blog($post->site_ID);
		}

	}

	function loop_end($query) {
		global $switched;
		if($query->get('multisite')) {
			$this->loop_end = true;
			if($switched) {
				switch_to_blog($this->blog_id);
			}
		}
	}
}

new WP_Query_Multisite();

?>