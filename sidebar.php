<?php
/**
 * The sidebar containing the main widget area.
 *
 * @package minimalizr
 */

$sidebar = array("sidebar-1", "sidebar-2", "sidebar-3", "sidebar-4");
$gridQuotient = 0;

	for($x = 0; $x < count($sidebar); $x++){
		
		if ( is_active_sidebar( $sidebar[$x] ) )
		{
			++$gridQuotient;
		}
	}
	
	if($gridQuotient > 0):

?>

<div id="secondary" class="widget-area clearfix" role="complementary">
	<div class="pure-g gutters">
		<?php for($i = 0; $i < count($sidebar); $i++) :?>
			<?php if ( is_active_sidebar( $sidebar[$i] ) ) : ?>
				<div class="pure-u-1 pure-u-md-1-2 pure-u-lg-1-<?php echo $gridQuotient; ?>"><?php dynamic_sidebar($sidebar[$i]); ?></div>
			<?php endif; ?>
		<?php endfor; ?>
	</div><!-- .pure-g -->
</div><!-- #secondary -->

<?php endif; ?>

