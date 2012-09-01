<?php

class P2P_Indeterminate_Connection_Type extends P2P_Directed_Connection_Type {

	function __construct( $ctype ) {
		$this->ctype = $ctype;
		$this->direction = 'any';
	}

	protected function recognize( $arg ) {
		foreach ( array( 'current', 'opposite' ) as $side ) {
			$item = $this->get( $side, 'side' )->item_recognize( $arg );
			if ( $item )
				return $item;
		}

		return false;
	}

	protected function get_non_connectable( $item, $extra_qv ) {
		$to_exclude = parent::get_non_connectable( $item, $extra_qv );

		if ( !$this->self_connections )
			$to_exclude[] = $item->get_id();

		return $to_exclude;
	}
}
