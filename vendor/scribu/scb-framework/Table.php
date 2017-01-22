<?php
/**
 * Takes care of creating, updating and deleting database tables.
 */
class scbTable {

	/**
	 * The table name.
	 * @var string
	 */
	protected $name;

	/**
	 * The table columns.
	 * @var string
	 */
	protected $columns;

	/**
	 * The upgrade method.
	 * @var string
	 */
	protected $upgrade_method;

	/**
	 * Sets up table.
	 *
	 * @param string $name Table name.
	 * @param string $file Reference to main plugin file.
	 * @param string $columns The SQL columns for the CREATE TABLE statement.
	 * @param array $upgrade_method (optional)
	 *
	 * @return void
	 */
	public function __construct( $name, $file, $columns, $upgrade_method = 'dbDelta' ) {
		$this->name = $name;
		$this->columns = $columns;
		$this->upgrade_method = $upgrade_method;

		scb_register_table( $name );

		if ( $file ) {
			scbUtil::add_activation_hook( $file, array( $this, 'install' ) );
			scbUtil::add_uninstall_hook( $file, array( $this, 'uninstall' ) );
		}
	}

	/**
	 * Installs table.
	 *
	 * @return void
	 */
	public function install() {
		scb_install_table( $this->name, $this->columns, $this->upgrade_method );
	}

	/**
	 * Uninstalls table.
	 *
	 * @return void
	 */
	public function uninstall() {
		scb_uninstall_table( $this->name );
	}
}

