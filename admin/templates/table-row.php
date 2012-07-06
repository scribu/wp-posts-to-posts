<tr>
	<?php foreach ( $columns as $o ) : extract( $o ); ?>
	<td class="p2p-col-<?php echo $column; ?>"><?php echo $content; ?></td>
	<?php endforeach; ?>
</tr>
