<div class="p2p-box" <?php echo $attributes; ?>>
	<?php if ( $connections ) : extract( $connections ); ?>
	<table class="p2p-connections" <?php echo $hide; ?>>
		<thead>
			<tr>
				<?php foreach ( $thead as $o ) : extract( $o ); ?>
				<th class="p2p-col-<?php echo $column; ?>"><?php echo $title; ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<tbody>
			<?php foreach ( $tbody as $o ) : extract( $o ); ?>
			<?php require dirname(__FILE__) . '/table-row.php'; ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<?php if ( $create_connections ) : extract( $create_connections ); ?>
	<div class="p2p-create-connections" <?php echo $hide; ?>>
		<p><strong><?php _e( 'Create connections:', P2P_TEXTDOMAIN ); ?></strong></p>
		<?php if ( count( $tabs ) > 1 ) : ?>
		<ul class="wp-tab-bar clearfix">
			<?php foreach ( $tabs as $o ) : extract( $o ); ?>
			<li<?php if ( $is_active ) { ?> class="wp-tab-active"<?php } ?> data-ref=".p2p-tab-<?php echo $tab_id; ?>"><a href="#"><?php echo $tab_title; ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>

		<?php foreach ( $tabs as $o ) : extract( $o ); ?>
		<div class="p2p-tab-<?php echo $tab_id; ?> tabs-panel">
			<?php echo $tab_content; ?>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>
