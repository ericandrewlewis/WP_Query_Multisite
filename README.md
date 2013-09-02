# WordPress Query Multisite

WP_Query_Multisite is a subclass of WP_Query, WordPress' post querying class. The class does everything behind the scenes, so the only change you make to query multisite is the in the class declaration expression.

----------------

This is a custom version to support multisite queries without the need of calling a subclass. Just by entering the custom query var **multisite => 1** on your query args your query is now global.

-----------------

## Example usage

```php
$query = new WP_Query( array('multisite' => '1' ) );
while($query->have_posts()) : $query->the_post();
    echo $blog_id . get_the_title() . "<BR>";
endwhile; 
wp_reset_postdata();
```

To modify what sites are queried, create a 'sites' element in the $args in the constructor parameter, with a sub-element of either 'sites__in' or 'sites__not_in', which will be an array similar to 'posts__in' in the WP_Query object. 

```php
$args = array(
	'multisite' => 1,
	'sites' => array(
		'sites__in' => array( 1, 2, 3, 5)
	)
);
$query = new WP_Query( $args );
while($query->have_posts()) : $query->the_post();
    echo $blog_id . get_the_title() . "<BR>";
endwhile; 
wp_reset_postdata
```