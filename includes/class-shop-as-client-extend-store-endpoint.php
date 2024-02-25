<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for extending the WooCommerce Store API
 */
class ShopAsClient_Extend_Store_Endpoint {

	/**
	 * Extension name.
	 *
	 * @var string
	 */
	public static $name = 'ptwoo-shop-as-client';

	/**
	 * The name of the extension.
	 *
	 * @return string
	 */
	public function get_name() {
		return static::$name;
	}

	/**
	 * When called invokes any initialization/setup for the extension.
	 */
	public function initialize() {
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => $this->get_name(),
				'callback'  => array( $this, 'store_api_update_callback' ),
			)
		);

		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_order' ) );
	}

	/**
	 * Add Store API schema data.
	 *
	 * @return array
	 */
	public function store_api_schema_callback() {
		return array();
	}

	/**
	 * Add Store API endpoint data.
	 *
	 * @return array
	 */
	public function store_api_data_callback() {
		return array();
	}

	/**
	 * Update callback to be executed by the Store API.
	 *
	 * @param  array $data Extension data.
	 * @return void
	 */
	public function store_api_update_callback( $data ) {

		if ( ! ( isset( wc()->session ) && wc()->session->has_session() ) ) {
			wc()->session->set_customer_session_cookie( true );
		}

		$shop_as_client = isset( $data['shopAsClient'] ) ? $data['shopAsClient'] : null;
		$create_user    = isset( $data['createUser'] ) ? $data['createUser'] : null;

		wc()->session->set( $this->get_name() . '_shop_as_client', $shop_as_client );
		wc()->session->set( $this->get_name() . '_create_user', $create_user );
	}

	/**
	 * Process order.
	 *
	 * @param  \WC_Order $order Order object.
	 * @return void
	 */
	public function process_order( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! shop_as_client_can_checkout() ) {
			return;
		}

		$shop_as_client = wc()->session->get( $this->get_name() . '_shop_as_client', false );
		$create_user    = wc()->session->get( $this->get_name() . '_create_user', false );

		if ( ! $shop_as_client ) {
			return;
		}

		$user_id    = 0;
		$user_email = $order->get_billing_email();

		if ( empty( $user_email ) ) {
			$user_email = apply_filters( 'shop_as_client_user_email_if_empty', $user_email, $order );
		}

		if ( empty( $user_email ) ) {
			return;
		}

		$user = get_user_by( 'email', $user_email );

		if ( $user instanceof \WP_User ) {
			$user_id = $user->ID;
		} else {
			$users = get_users(
				array(
					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'meta_key'     => 'billing_email',
					'meta_value'   => $user_email,
					'meta_compare' => '=',
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);

			if ( ! empty( $users ) ) {
				$user    = reset( $users );
				$user_id = $user->ID;
			} elseif ( $create_user ) {
				$user_id = shop_as_client_create_customer(
					$user_email,
					$order->get_billing_first_name(),
					$order->get_billing_last_name()
				);
			}
		}

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error(
				'invalid_order_update_status',
				sprintf(
					/* translators: %s error message. */
					__( 'Shop as Client failed to create user: %s', 'shop-as-client' ),
					$user_id->get_error_message()
				)
			);
		}

		$order->set_customer_id( $user_id );
		$order->save();

		do_action( 'shop_as_client_store_api_checkout_order_processed', $order, $user_id );

		// Clear the extension's session data.
		wc()->session->__unset( $this->get_name() . '_shop_as_client' );
		wc()->session->__unset( $this->get_name() . '_create_user' );
	}
}
