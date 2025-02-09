<?php
/**
 * Class description
 *
 * @package   package_name
 * @author    Softhopper
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'SoftTemplate_Widget_Integration' ) ) {

	/**
	 * Define SoftTemplate_Widget_Integration class
	 */
	class SoftTemplate_Widget_Integration {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Check if processing elementor widget
		 *
		 * @var boolean
		 */
		private $is_elementor_ajax = false;

		/**
		 * Initalize integration hooks
		 *
		 * @return void
		 */
		public function init() {

			add_action( 'elementor/init', array( $this, 'register_category' ) );

			add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ), 10 );

			add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_vendor_widgets' ), 20 );

			//add_action( 'elementor/controls/controls_registered', array( $this, 'add_controls' ), 10 );

			//add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'font_styles' ) );
			//add_action( 'elementor/preview/enqueue_styles',      array( $this, 'font_styles' ) );

		}

		/**
		 * Enqueue icon font styles
		 *
		 * @return void
		 */
		public function font_styles() {

			wp_enqueue_style(
				'soft-template-core-font',
				soft_template_core()->plugin_url( 'assets/css/soft-template-core-icons.css' ),
				array(),
				soft_template_core()->get_version()
			);

		}

		/**
		 * Check if we currently in Elementor mode
		 *
		 * @return void
		 */
		public function in_elementor() {

			$result = false;

			if ( wp_doing_ajax() ) {
				$result = ( isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] );
			} elseif ( Elementor\Plugin::instance()->editor->is_edit_mode()
				&& 'wp_enqueue_scripts' === current_filter() ) {
				$result = true;
			} elseif ( Elementor\Plugin::instance()->preview->is_preview_mode() && 'wp_enqueue_scripts' === current_filter() ) {
				$result = true;
			}

			/**
			 * Allow to filter result before return
			 *
			 * @var bool $result
			 */
			return apply_filters( 'soft-template-core/in-elementor', $result );
		}

		/**
		 * Add new controls.
		 *
		 * @param  object $controls_manager Controls manager instance.
		 * @return void
		 */
		public function add_controls( $controls_manager ) {

			$grouped = array(
				'soft-template-core-box-style' => 'SoftTemplate_Group_Control_Box_Style',
			);

			foreach ( $grouped as $control_id => $class_name ) {
				if ( $this->include_control( $class_name, true ) ) {
					$controls_manager->add_group_control( $control_id, new $class_name() );
				}
			}

		}

		/**
		 * Include control file by class name.
		 *
		 * @param  [type] $class_name [description]
		 * @return [type]             [description]
		 */
		public function include_control( $class_name, $grouped = false ) {

			$filename = sprintf(
				'includes/controls/%2$sclass-%1$s.php',
				str_replace( '_', '-', strtolower( $class_name ) ),
				( true === $grouped ? 'groups/' : '' )
			);

			if ( ! file_exists( soft_template_core()->plugin_path( $filename ) ) ) {
				return false;
			}

			require soft_template_core()->plugin_path( $filename );

			return true;
		}

		/**
		 * Register plugin widgets
		 *
		 * @param  object $widgets_manager Elementor widgets manager instance.
		 * @return void
		 */
		public function register_widgets( $widgets_manager ) {

			$avaliable_widgets = soft_template_core()->settings->get( 'softemplate_available_widgets' );

			require soft_template_core()->plugin_path( 'includes/base/class-soft-template-core-base.php' );

			
			foreach ( glob( soft_template_core()->plugin_path( 'includes/widgets/' ) . '*.php' ) as $file ) {
				
				$slug    = basename( $file, '.php' );
				$enabled = isset( $avaliable_widgets[ $slug ] ) ? $avaliable_widgets[ $slug ] : '';
				
				if ( filter_var( $enabled, FILTER_VALIDATE_BOOLEAN ) || ! $avaliable_widgets ) {
					$this->register_widget( $file, $widgets_manager );
				}
			}
		}

		/**
		 * Register vendor widgets
		 *
		 * @param  object $widgets_manager Elementor widgets manager instance.
		 * @return void
		 */
		public function register_vendor_widgets( $widgets_manager ) {

			$woo_conditional = array(
				'cb'  => 'class_exists',
				'arg' => 'WooCommerce',
			);

			$allowed_vendors = apply_filters(
				'soft-template-core/allowed-vendor-widgets',
				array(
					'woo_cart' => array(
						'file' => soft_template_core()->plugin_path(
							'includes/widgets/vendor/soft-template-core-woo-cart.php'
						),
						'conditional' => $woo_conditional,
					),
				)
			);

			foreach ( $allowed_vendors as $vendor ) {
				if ( is_callable( $vendor['conditional']['cb'] )
					&& true === call_user_func( $vendor['conditional']['cb'], $vendor['conditional']['arg'] ) ) {
					$this->register_widget( $vendor['file'], $widgets_manager );
				}
			}
		}

		/**
		 * Register addon by file name
		 *
		 * @param  string $file            File name.
		 * @param  object $widgets_manager Widgets manager instance.
		 * @return void
		 */
		public function register_widget( $file, $widgets_manager ) {

			$base  = basename( str_replace( '.php', '', $file ) );
			$class = ucwords( str_replace( '-', ' ', $base ) );
			$class = str_replace( ' ', '_', $class );
			$class = sprintf( 'Elementor\%s', $class );

			require $file;

			if ( class_exists( $class ) ) {
				$widgets_manager->register_widget_type( new $class );
			}
		}

		/**
		 * Register cherry category for elementor if not exists
		 *
		 * @return void
		 */
		public function register_category() {

			$elements_manager = Elementor\Plugin::instance()->elements_manager;
			$cherry_cat       = 'soft-template-core';

			$elements_manager->add_category(
				$cherry_cat,
				array(
					'title' => esc_html__( 'Soft Templates', 'soft-template-core' ),
					'icon'  => 'font',
				),
				2
			);
		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance( $shortcodes = array() ) {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self( $shortcodes );
			}
			return self::$instance;
		}
	}

}

/**
 * Returns instance of SoftTemplate_Widget_Integration
 *
 * @return object
 */
function soft_template_widget_integration() {
	return SoftTemplate_Widget_Integration::get_instance();
}