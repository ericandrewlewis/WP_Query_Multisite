`WP_Query_Multisite` is a subclass of `WP_Query`, WordPress' post querying class. The class does everything behind the scenes, so the only change you make to query multisite is the in the class declaration expression. 


Example usage:

```php
$query = new WP_Query_Multisite( array( 'post_type' => 'post' ) );

while( $query->have_posts() ) : $query->the_post();
    echo $blog_id . get_the_title() . "<br>";
endwhile;

wp_reset_postdata();
```

To modify what sites are queried, create a `sites` key in the `$args` in the constructor parameter, with a sub-element of either `sites__in` or `sites__not_in`, which will be an `array` similar to `posts__in` in the `WP_Query` object. 

Example usage:
 
```php
$args = array(
	'post_type' => 'post',
	'sites' => array(
		'sites__in' => array( 1, 2, 3, 5 )
	)
);

$query = new WP_Query_Multisite( $args );

while( $query->have_posts() ) : $query->the_post();
    echo $blog_id . get_the_title() . "<br>";
endwhile; 

wp_reset_postdata();
```

# Alternative
We do want to suggest that this [version / fork](https://github.com/miguelpeixe/WP_Query_Multisite) uses `pre_get_post` and may be a better solution for you. This way you can keep using the good 'ol `WP_Query` without editing any theme files.  
[https://github.com/miguelpeixe/WP_Query_Multisite](https://github.com/miguelpeixe/WP_Query_Multisite)
