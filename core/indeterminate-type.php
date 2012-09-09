<?php

class P2P_Indeterminate_Connection_Type extends P2P_Directed_Connection_Type {

	protected function recognize( $arg ) {
		foreach ( array( 'current', 'opposite' ) as $side ) {
			$item = $this->get( $side, 'side' )->item_recognize( $arg );
			if ( $item )
				return $item;
		}

		return false;
	}

	public function get_final_qv( $q ) {
		$side = $this->get( 'current', 'side' );

		// the sides are of the same type, so just use one for translating
		$q = $side->translate_qv( $q );

		$args = $side->get_base_qv( $q );

		$other_qv = $this->get( 'opposite', 'side' )->get_base_qv( $q );

		// need to be inclusive
		if ( isset( $other_qv['post_type'] ) )
			_p2p_append( $args['post_type'], $other_qv['post_type'] );

		return $args;
	}

	protected function get_non_connectable( $item, $extra_qv ) {
		$to_exclude = parent::get_non_connectable( $item, $extra_qv );

		if ( !$this->self_connections )
			$to_exclude[] = $item->get_id();

		return $to_exclude;
	}
}
