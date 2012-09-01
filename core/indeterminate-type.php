<?php

class P2P_Indeterminate_Type extends P2P_Directed_Type {

	protected function item_recognize( $item, $side = 'current' ) {
		return
			$this->get( 'current', 'side' )->item_recognize( $item ) ||
			$this->get( 'opposite', 'side' )->item_recognize( $item );
	}
}
