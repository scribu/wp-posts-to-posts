<?php

class P2P_Indeterminate_Connection_Type extends P2P_Directed_Connection_Type {

	public function accepts_single_connection() {
		return 'one' == $this->cardinality && 'one' == $this->other_cardinality;
	}
}

