<?php

class msgwow extends WP_SMS {
	private $wsdl_link = "http://my.msgwow.com/api/";
	public $tariff = "http://msgwow.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "919999999999";
		$this->help           = "Login authentication key (this key is unique for every user).<br>The default route number is 4 and you can set your route number in sender number. e.g. 100000:4 or 100000:2";
		$this->has_key        = true;
	}

	public function SendSMS() {
		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			return new WP_Error( 'account-credit', __( 'Your account does not credit for sending sms.', 'wp-sms' ) );
		}

		/**
		 * Modify sender number
		 *
		 * @since 3.4
		 *
		 * @param string $this ->from sender number.
		 */
		$this->from = apply_filters( 'wp_sms_from', $this->from );

		/**
		 * Modify Receiver number
		 *
		 * @since 3.4
		 *
		 * @param array $this ->to receiver number
		 */
		$this->to = apply_filters( 'wp_sms_to', $this->to );

		/**
		 * Modify text message
		 *
		 * @since 3.4
		 *
		 * @param string $this ->msg text message.
		 */
		$this->msg = apply_filters( 'wp_sms_msg', $this->msg );

		// Implode numbers
		$to = implode( ',', $this->to );

		$from = explode( ':', $this->from );
		if ( is_array( $from ) and isset($from[1]) ) {
			$route = $from[1];
		} else {
			$route = 4;
		}

		// Unicode message
		$msg = urlencode( $this->msg );

		$response = wp_remote_get( $this->wsdl_link . "v2/sendsms?authkey=" . $this->has_key . "&mobiles=" . $to . "&message=" . $msg . "&sender=" . $this->from . "&route=" . $route . "&country=0", array( 'timeout' => 30 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$result        = json_decode( $response['body'] );

		if ( $response_code == '200' ) {
			if ( $result->type == 'success' ) {
				$this->InsertToDB( $this->from, $this->msg, $this->to );

				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 *
				 * @param string $result result output.
				 */
				do_action( 'wp_sms_send', $result );

				return $result;
			} else {
				return $result->message;
			}

		} else {
			return new WP_Error( 'send-sms', $result->message );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->has_key ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "balance.php?authkey=" . $this->has_key . "&type=1", array( 'timeout' => 30 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			if ( ! $response['body'] ) {
				return new WP_Error( 'account-credit', __( 'Server API Unavailable', 'wp-sms' ) );
			}

			$result = json_decode( $response['body'] );

			if ( isset( $result->msgType ) and $result->msgType == 'error' ) {
				return new WP_Error( 'account-credit', $result->msg . ' (See error codes: http://my.msgwow.com/apidoc/basic/error-code-basic.php)' );
			} else {
				return $result;
			}
		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}
	}
}